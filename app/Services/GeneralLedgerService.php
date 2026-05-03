<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Expense;
use App\Models\GlAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PayrollRun;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Double-entry GL: chart seeding, journal posting, and helpers for reports / reconciliation.
 */
final class GeneralLedgerService
{
    public const CODE_CASH = '1010';

    public const CODE_BANK = '1020';

    public const CODE_VAT_PAYABLE = '2010';

    public const CODE_PAYROLL_WITHHOLDINGS = '2030';

    public const CODE_SALES = '4010';

    public const CODE_OPEX = '5010';

    public const CODE_PAYROLL = '5020';

    public function ensureDefaultChart(Business $business): void
    {
        if (GlAccount::query()->where('business_id', $business->id)->exists()) {
            return;
        }

        $rows = [
            [self::CODE_CASH, 'Cash on hand', 'asset', 10],
            [self::CODE_BANK, 'Bank deposits', 'asset', 20],
            [self::CODE_VAT_PAYABLE, 'VAT payable', 'liability', 30],
            [self::CODE_PAYROLL_WITHHOLDINGS, 'Payroll withholdings payable', 'liability', 40],
            [self::CODE_SALES, 'Sales revenue', 'revenue', 50],
            [self::CODE_OPEX, 'Operating expenses', 'expense', 60],
            [self::CODE_PAYROLL, 'Payroll expense', 'expense', 70],
        ];

        DB::transaction(function () use ($business, $rows): void {
            foreach ($rows as [$code, $name, $type, $sort]) {
                GlAccount::query()->create([
                    'business_id' => $business->id,
                    'uuid' => (string) Str::uuid(),
                    'code' => $code,
                    'name' => $name,
                    'type' => $type,
                    'parent_id' => null,
                    'is_system' => true,
                    'is_active' => true,
                    'sort_order' => $sort,
                ]);
            }
        });
    }

    /**
     * Next unused numeric GL code for the given type (steps by 10). Codes are unique per business across all types.
     */
    public function allocateNextAccountCode(Business $business, string $type): string
    {
        $this->ensureDefaultChart($business);

        $starter = match ($type) {
            'asset' => 1030,
            'liability' => 2040,
            'equity' => 3010,
            'revenue' => 4020,
            'expense' => 5030,
            default => 9010,
        };

        $sameTypeMax = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('type', $type)
            ->get()
            ->map(fn (GlAccount $a) => ctype_digit(trim($a->code)) ? (int) trim($a->code) : null)
            ->filter()
            ->max();

        $next = ($sameTypeMax !== null && $sameTypeMax > 0) ? $sameTypeMax + 10 : $starter;

        $globalMax = GlAccount::query()
            ->where('business_id', $business->id)
            ->pluck('code')
            ->map(fn (string $c) => ctype_digit(trim($c)) ? (int) trim($c) : null)
            ->filter()
            ->max();

        if ($globalMax !== null && $next <= $globalMax) {
            $next = $globalMax + 10;
        }

        while (GlAccount::query()->where('business_id', $business->id)->where('code', (string) $next)->exists()) {
            $next += 10;
        }

        return (string) $next;
    }

    public function voidBySource(Business $business, string $sourceType, string $sourceUuid): void
    {
        JournalEntry::query()
            ->where('business_id', $business->id)
            ->where('source_type', $sourceType)
            ->where('source_uuid', $sourceUuid)
            ->delete();
    }

    public function postSaleJournal(Business $business, Sale $sale): void
    {
        $this->ensureDefaultChart($business);
        $sale->loadMissing('payments');

        $key = 'sale:'.$sale->uuid;
        if (JournalEntry::query()->where('business_id', $business->id)->where('idempotency_key', $key)->exists()) {
            return;
        }

        $cash = 0.0;
        $bank = 0.0;
        foreach ($sale->payments as $p) {
            $a = (float) $p->amount;
            if ($p->method === 'cash') {
                $cash += $a;
            } else {
                $bank += $a;
            }
        }

        $subtotal = (float) $sale->subtotal;
        $tax = (float) $sale->tax_total;
        $discount = (float) $sale->discount_total;
        $netSales = round(max(0, $subtotal - $discount), 2);
        $grand = (float) $sale->grand_total;

        $aCash = $this->account($business, self::CODE_CASH);
        $aBank = $this->account($business, self::CODE_BANK);
        $aSales = $this->account($business, self::CODE_SALES);
        $aVat = $this->account($business, self::CODE_VAT_PAYABLE);

        DB::transaction(function () use ($business, $sale, $key, $cash, $bank, $netSales, $tax, $grand, $aCash, $aBank, $aSales, $aVat): void {
            $entry = JournalEntry::query()->create([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'entry_date' => $sale->sold_at?->toDateString() ?? now()->toDateString(),
                'posted_at' => now(),
                'memo' => 'POS sale '.$sale->receipt_no,
                'source_type' => 'sale',
                'source_uuid' => $sale->uuid,
                'idempotency_key' => $key,
            ]);

            $lines = [];
            if ($cash > 0) {
                $lines[] = ['gl_account_id' => $aCash->id, 'debit' => $cash, 'credit' => 0, 'description' => 'Cash takings'];
            }
            if ($bank > 0) {
                $lines[] = ['gl_account_id' => $aBank->id, 'debit' => $bank, 'credit' => 0, 'description' => 'Card / transfer takings'];
            }
            $lines[] = ['gl_account_id' => $aSales->id, 'debit' => 0, 'credit' => $netSales, 'description' => 'Sales (ex VAT, net of discount)'];
            if ($tax > 0) {
                $lines[] = ['gl_account_id' => $aVat->id, 'debit' => 0, 'credit' => $tax, 'description' => 'Output VAT'];
            }

            $this->insertLines($entry, $lines);
            $this->assertBalanced($entry);
            $this->assertAmountMatches($grand, $entry);
        });
    }

    public function postExpenseJournal(Business $business, Expense $expense): void
    {
        $this->ensureDefaultChart($business);
        $key = 'expense:'.$expense->uuid;
        if (JournalEntry::query()->where('business_id', $business->id)->where('idempotency_key', $key)->exists()) {
            return;
        }

        $amount = (float) $expense->amount;
        $opex = $this->account($business, self::CODE_OPEX);
        $cash = $this->account($business, self::CODE_CASH);

        DB::transaction(function () use ($business, $expense, $key, $amount, $opex, $cash): void {
            $entry = JournalEntry::query()->create([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'entry_date' => $expense->paid_at?->toDateString() ?? now()->toDateString(),
                'posted_at' => now(),
                'memo' => 'Expense: '.($expense->category ?? 'General'),
                'source_type' => 'expense',
                'source_uuid' => $expense->uuid,
                'idempotency_key' => $key,
            ]);

            $this->insertLines($entry, [
                ['gl_account_id' => $opex->id, 'debit' => $amount, 'credit' => 0, 'description' => $expense->notes],
                ['gl_account_id' => $cash->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Paid from cash'],
            ]);
            $this->assertBalanced($entry);
        });
    }

    public function postPayrollJournal(Business $business, PayrollRun $run): void
    {
        $this->ensureDefaultChart($business);
        $run->loadMissing('lines');
        $key = 'payroll:'.$run->uuid;
        if (JournalEntry::query()->where('business_id', $business->id)->where('idempotency_key', $key)->exists()) {
            return;
        }

        $gross = (float) $run->lines->sum(fn ($l) => (float) $l->gross_salary);
        $net = (float) $run->lines->sum(fn ($l) => (float) $l->net_salary);
        if ($gross <= 0) {
            return;
        }

        $withholdings = round($gross - $net, 2);
        $payroll = $this->account($business, self::CODE_PAYROLL);
        $cash = $this->account($business, self::CODE_CASH);
        $wh = $this->account($business, self::CODE_PAYROLL_WITHHOLDINGS);

        DB::transaction(function () use ($business, $run, $key, $gross, $net, $withholdings, $payroll, $cash, $wh): void {
            $entry = JournalEntry::query()->create([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'entry_date' => $run->period_on ?? now()->toDateString(),
                'posted_at' => now(),
                'memo' => 'Payroll finalised '.$run->period_on,
                'source_type' => 'payroll',
                'source_uuid' => $run->uuid,
                'idempotency_key' => $key,
            ]);

            $lines = [
                ['gl_account_id' => $payroll->id, 'debit' => $gross, 'credit' => 0, 'description' => 'Payroll cost (gross)'],
                ['gl_account_id' => $cash->id, 'debit' => 0, 'credit' => $net, 'description' => 'Net pay (cash disbursed)'],
            ];
            if ($withholdings > 0) {
                $lines[] = ['gl_account_id' => $wh->id, 'debit' => 0, 'credit' => $withholdings, 'description' => 'Withholdings & employer charges (net of cash paid)'];
            }
            $this->insertLines($entry, $lines);
            $this->assertBalanced($entry);
        });
    }

    /**
     * @param  array<int, array{gl_account_id: int, debit: float, credit: float, description?: string|null}>  $lines
     */
    public function createManualEntry(Business $business, string $entryDate, ?string $memo, array $lines): JournalEntry
    {
        $this->ensureDefaultChart($business);
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }

        return DB::transaction(function () use ($business, $entryDate, $memo, $lines): JournalEntry {
            $entry = JournalEntry::query()->create([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'entry_date' => $entryDate,
                'posted_at' => now(),
                'memo' => $memo,
                'source_type' => 'manual',
                'source_uuid' => null,
                'idempotency_key' => 'manual:'.Str::uuid()->toString(),
            ]);
            $this->insertLines($entry, $lines);
            $this->assertBalanced($entry);

            return $entry->fresh('lines.account');
        });
    }

    /**
     * @return array<int, array{account: GlAccount, debit: string, credit: string, net: float}>
     */
    public function trialBalance(Business $business, string $asOfDate): array
    {
        $this->ensureDefaultChart($business);

        $rows = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $sums = JournalLine::query()
            ->selectRaw('gl_account_id, SUM(debit) as dr, SUM(credit) as cr')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.business_id', $business->id)
            ->whereDate('journal_entries.entry_date', '<=', $asOfDate)
            ->groupBy('gl_account_id')
            ->get()
            ->keyBy('gl_account_id');

        $out = [];
        foreach ($rows as $acc) {
            $s = $sums->get($acc->id);
            $dr = $s ? (float) $s->dr : 0.0;
            $cr = $s ? (float) $s->cr : 0.0;
            $net = round($dr - $cr, 2);
            $out[] = [
                'uuid' => $acc->uuid,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'debit' => $dr,
                'credit' => $cr,
                'net_dr_minus_cr' => $net,
            ];
        }

        return $out;
    }

    private function account(Business $business, string $code): GlAccount
    {
        return GlAccount::query()
            ->where('business_id', $business->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * @param  array<int, array{gl_account_id: int, debit: float, credit: float, description?: string|null}>  $lines
     */
    private function insertLines(JournalEntry $entry, array $lines): void
    {
        foreach ($lines as $l) {
            JournalLine::query()->create([
                'uuid' => (string) Str::uuid(),
                'journal_entry_id' => $entry->id,
                'gl_account_id' => $l['gl_account_id'],
                'description' => $l['description'] ?? null,
                'debit' => $l['debit'],
                'credit' => $l['credit'],
            ]);
        }
    }

    private function assertBalanced(JournalEntry $entry): void
    {
        $entry->load('lines');
        $dr = (float) $entry->lines->sum(fn (JournalLine $l) => (float) $l->debit);
        $cr = (float) $entry->lines->sum(fn (JournalLine $l) => (float) $l->credit);
        if (round($dr, 2) !== round($cr, 2)) {
            throw new InvalidArgumentException('Journal entry is not balanced.');
        }
    }

    private function assertAmountMatches(float $grand, JournalEntry $entry): void
    {
        $entry->load('lines');
        $dr = (float) $entry->lines->sum(fn (JournalLine $l) => (float) $l->debit);
        if (round($dr, 2) !== round($grand, 2)) {
            throw new InvalidArgumentException('Sale journal debits do not match grand total.');
        }
    }

    public function assertBankAccountBelongs(BankAccount $bank, Business $business): void
    {
        abort_unless((int) $bank->business_id === (int) $business->id, 404);
    }
}
