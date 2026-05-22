<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Expense;
use App\Models\GlAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\PayrollRun;
use App\Models\PurchaseOrder;
use App\Models\PurchasePayment;
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

    public const CODE_AR = '1030';

    public const CODE_INVENTORY = '1040';

    public const CODE_AP = '2020';

    public const CODE_VAT_PAYABLE = '2010';

    public const CODE_PAYROLL_WITHHOLDINGS = '2030';

    public const CODE_SALES = '4010';

    public const CODE_OPEX = '5010';

    public const CODE_PAYROLL = '5020';

    public function ensureDefaultChart(Business $business): void
    {
        $rows = [
            [self::CODE_CASH, 'Cash on hand', 'asset', 10],
            [self::CODE_BANK, 'Bank deposits', 'asset', 20],
            [self::CODE_AR, 'Accounts receivable', 'asset', 25],
            [self::CODE_INVENTORY, 'Inventory', 'asset', 28],
            [self::CODE_AP, 'Accounts payable', 'liability', 35],
            [self::CODE_VAT_PAYABLE, 'VAT payable', 'liability', 30],
            [self::CODE_PAYROLL_WITHHOLDINGS, 'Payroll withholdings payable', 'liability', 40],
            [self::CODE_SALES, 'Sales revenue', 'revenue', 50],
            [self::CODE_OPEX, 'Operating expenses', 'expense', 60],
            [self::CODE_PAYROLL, 'Payroll expense', 'expense', 70],
        ];

        DB::transaction(function () use ($business, $rows): void {
            foreach ($rows as [$code, $name, $type, $sort]) {
                $exists = GlAccount::query()
                    ->where('business_id', $business->id)
                    ->where('code', $code)
                    ->exists();
                if ($exists) {
                    continue;
                }
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
        $sale->loadMissing('payments.glAccount');

        $key = 'sale:'.$sale->uuid;
        if (JournalEntry::query()->where('business_id', $business->id)->where('idempotency_key', $key)->exists()) {
            return;
        }

        $credit = 0.0;
        $assetLines = [];
        foreach ($sale->payments as $p) {
            $a = (float) $p->amount;
            if ($a <= 0) {
                continue;
            }
            if ($p->method === 'credit') {
                $credit += $a;
            } else {
                $gl = $p->glAccount ?: $this->account(
                    $business,
                    $p->method === 'cash' ? self::CODE_CASH : self::CODE_BANK,
                );
                $assetKey = (int) $gl->id;
                if (! isset($assetLines[$assetKey])) {
                    $assetLines[$assetKey] = [
                        'gl_account_id' => $gl->id,
                        'debit' => 0.0,
                        'credit' => 0,
                        'description' => 'Sale payment to '.$gl->name,
                    ];
                }
                $assetLines[$assetKey]['debit'] = round((float) $assetLines[$assetKey]['debit'] + $a, 2);
            }
        }

        $subtotal = (float) $sale->subtotal;
        $tax = (float) $sale->tax_total;
        $discount = (float) $sale->discount_total;
        $netSales = round(max(0, $subtotal - $discount), 2);
        $grand = (float) $sale->grand_total;

        $aAr = $this->account($business, self::CODE_AR);
        $aSales = $this->account($business, self::CODE_SALES);
        $aVat = $this->account($business, self::CODE_VAT_PAYABLE);

        DB::transaction(function () use ($business, $sale, $key, $assetLines, $credit, $netSales, $tax, $grand, $aAr, $aSales, $aVat): void {
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

            $lines = array_values($assetLines);
            if ($credit > 0) {
                $lines[] = ['gl_account_id' => $aAr->id, 'debit' => $credit, 'credit' => 0, 'description' => 'On credit (Accounts receivable)'];
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

    /**
     * Customer payment against outstanding credit balance.
     * Dr cash/bank, Cr Accounts receivable.
     */
    public function postCustomerCreditPayment(
        Business $business,
        \App\Models\CustomerCreditEntry $entry,
    ): void {
        $this->ensureDefaultChart($business);

        $key = 'customer_credit_payment:'.$entry->uuid;
        if (JournalEntry::query()->where('business_id', $business->id)->where('idempotency_key', $key)->exists()) {
            return;
        }

        $amount = (float) $entry->amount;
        if ($amount <= 0) {
            return;
        }

        $debitAccount = match ($entry->method) {
            'cash' => $this->account($business, self::CODE_CASH),
            default => $this->account($business, self::CODE_BANK),
        };
        $aAr = $this->account($business, self::CODE_AR);

        DB::transaction(function () use ($business, $entry, $key, $amount, $debitAccount, $aAr): void {
            $journal = JournalEntry::query()->create([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'entry_date' => $entry->occurred_at?->toDateString() ?? now()->toDateString(),
                'posted_at' => now(),
                'memo' => 'Customer credit payment '.$entry->uuid,
                'source_type' => 'customer_credit_payment',
                'source_uuid' => $entry->uuid,
                'idempotency_key' => $key,
            ]);

            $this->insertLines($journal, [
                ['gl_account_id' => $debitAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Customer payment received ('.$entry->method.')'],
                ['gl_account_id' => $aAr->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Reduce Accounts receivable'],
            ]);
            $this->assertBalanced($journal);
        });
    }

    public function postPurchaseJournal(Business $business, PurchaseOrder $po): void
    {
        $this->ensureDefaultChart($business);
        $po->loadMissing(['payments.glAccount', 'supplier']);
        $key = 'purchase:'.$po->uuid;
        if (JournalEntry::query()->where('business_id', $business->id)->where('idempotency_key', $key)->exists()) {
            return;
        }

        $total = round((float) $po->total, 2);
        if ($total <= 0) {
            return;
        }

        $inventory = $this->account($business, self::CODE_INVENTORY);
        $ap = $this->account($business, self::CODE_AP);
        $paid = round((float) $po->payments->sum(fn (PurchasePayment $p) => (float) $p->amount), 2);
        $onAccount = round(max($total - $paid, 0), 2);

        $lines = [
            ['gl_account_id' => $inventory->id, 'debit' => $total, 'credit' => 0, 'description' => 'Stock purchase received'],
        ];

        foreach ($po->payments as $payment) {
            $gl = $payment->glAccount;
            if ($gl === null) {
                continue;
            }
            $amt = round((float) $payment->amount, 2);
            if ($amt <= 0) {
                continue;
            }
            $label = $gl->name !== '' ? $gl->name : 'Funds account';
            $method = $payment->method === 'transfer' ? 'transfer' : ($payment->method === 'pos' ? 'POS' : 'cash');
            $lines[] = [
                'gl_account_id' => $gl->id,
                'debit' => 0,
                'credit' => $amt,
                'description' => "Paid via {$method} from {$label}",
            ];
        }

        if ($onAccount > 0.0001) {
            $supplierName = $po->supplier?->name ?? 'supplier';
            $lines[] = [
                'gl_account_id' => $ap->id,
                'debit' => 0,
                'credit' => $onAccount,
                'description' => "On account — {$supplierName}",
            ];
        }

        DB::transaction(function () use ($business, $po, $key, $lines): void {
            $entry = JournalEntry::query()->create([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'entry_date' => $po->ordered_at?->toDateString() ?? now()->toDateString(),
                'posted_at' => now(),
                'memo' => 'Purchase received',
                'source_type' => 'purchase_order',
                'source_uuid' => $po->uuid,
                'idempotency_key' => $key,
            ]);

            $this->insertLines($entry, $lines);
            $this->assertBalanced($entry);
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

        // Credit the account the business paid from. When the expense pre-dates
        // the account-picker feature (or none was selected), default to cash.
        $fundAccount = null;
        if ($expense->gl_account_id !== null) {
            $fundAccount = GlAccount::query()
                ->where('business_id', $business->id)
                ->where('id', $expense->gl_account_id)
                ->first();
        }
        if ($fundAccount === null) {
            $fundAccount = $this->account($business, self::CODE_CASH);
        }
        $fundLabel = $fundAccount->name !== ''
            ? 'Paid from '.$fundAccount->name
            : 'Paid from cash';

        DB::transaction(function () use ($business, $expense, $key, $amount, $opex, $fundAccount, $fundLabel): void {
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
                ['gl_account_id' => $fundAccount->id, 'debit' => 0, 'credit' => $amount, 'description' => $fundLabel],
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
        // Sub-cent float artefacts can survive even after 2dp rounding when many lines
        // are summed; SaleCheckoutService already normalises payment amounts to match
        // grand_total exactly, so anything bigger than 0.5 cents is a real bug.
        if (abs(round($dr, 2) - round($grand, 2)) > 0.005) {
            throw new InvalidArgumentException(
                'Sale journal debits ('.number_format($dr, 2)
                .') do not match grand total ('.number_format($grand, 2).').'
            );
        }
    }

    public function assertBankAccountBelongs(BankAccount $bank, Business $business): void
    {
        abort_unless((int) $bank->business_id === (int) $business->id, 404);
    }
}
