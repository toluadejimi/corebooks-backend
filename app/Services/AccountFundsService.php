<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Business;
use App\Models\GlAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Funds accounts (cash + bank): list balances, transfer between accounts, record
 * deposits and withdrawals. Every movement posts a balanced journal entry through
 * GeneralLedgerService so trial balance stays correct.
 */
final class AccountFundsService
{
    public const CODE_OWNER_CONTRIBUTIONS = '3010';

    public const CODE_OWNER_DRAWINGS = '3020';

    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    /**
     * Owner contributions / drawings equity accounts are created on first use so
     * deposit / withdraw stay double-entry without requiring chart edits.
     */
    public function ensureEquityAccounts(Business $business): void
    {
        $this->ledger->ensureDefaultChart($business);

        $rows = [
            [self::CODE_OWNER_CONTRIBUTIONS, 'Owner contributions', 'equity', 80],
            [self::CODE_OWNER_DRAWINGS, 'Owner drawings', 'equity', 90],
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
     * Manageable accounts: default Cash on hand (GL 1010) + every BankAccount row.
     *
     * @return array<int, array{
     *     uuid: string,
     *     name: string,
     *     kind: string,
     *     currency: string,
     *     gl_code: string,
     *     gl_account_uuid: string,
     *     balance: float,
     *     is_active: bool,
     *     bank_uuid: ?string
     * }>
     */
    public function listAccounts(Business $business): array
    {
        $this->ensureEquityAccounts($business);

        $out = [];

        $cashGl = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('code', GeneralLedgerService::CODE_CASH)
            ->first();
        if ($cashGl !== null) {
            $out[] = [
                'uuid' => 'cash:'.$cashGl->uuid,
                'name' => 'Cash on hand',
                'kind' => 'cash',
                'currency' => (string) ($business->currency ?? 'NGN'),
                'gl_code' => (string) $cashGl->code,
                'gl_account_uuid' => $cashGl->uuid,
                'balance' => $this->glBalance($business, $cashGl->id),
                'is_active' => true,
                'bank_uuid' => null,
            ];
        }

        $bankAccounts = BankAccount::query()
            ->where('business_id', $business->id)
            ->with('glAccount')
            ->orderBy('name')
            ->get();

        foreach ($bankAccounts as $bank) {
            $gl = $bank->glAccount;
            if ($gl === null) {
                continue;
            }
            $out[] = [
                'uuid' => $bank->uuid,
                'name' => $bank->name,
                'kind' => 'bank',
                'currency' => (string) ($bank->currency ?? ($business->currency ?? 'NGN')),
                'gl_code' => (string) $gl->code,
                'gl_account_uuid' => $gl->uuid,
                'balance' => $this->glBalance($business, (int) $gl->id),
                'is_active' => (bool) $bank->is_active,
                'bank_uuid' => $bank->uuid,
            ];
        }

        return $out;
    }

    /**
     * Add a new managed account (e.g. a bank or till). Allocates a fresh GL asset
     * code and links the BankAccount row to it.
     */
    public function createAccount(Business $business, string $name, ?string $currency): BankAccount
    {
        $this->ensureEquityAccounts($business);
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Account name is required.');
        }
        $currency = trim((string) ($currency ?? '')) !== ''
            ? strtoupper(trim((string) $currency))
            : (string) ($business->currency ?? 'NGN');

        return DB::transaction(function () use ($business, $name, $currency): BankAccount {
            $code = $this->ledger->allocateNextAccountCode($business, 'asset');
            $nextSort = (int) (GlAccount::query()->where('business_id', $business->id)->max('sort_order') ?? 0) + 1;

            $gl = GlAccount::query()->create([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'type' => 'asset',
                'parent_id' => null,
                'is_system' => false,
                'is_active' => true,
                'sort_order' => $nextSort,
            ]);

            return BankAccount::query()->create([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'currency' => $currency,
                'gl_account_id' => $gl->id,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Move money between two manageable accounts. Dr destination / Cr source.
     */
    public function transfer(
        Business $business,
        string $fromGlUuid,
        string $toGlUuid,
        float $amount,
        ?string $date,
        ?string $memo,
    ): JournalEntry {
        if ($fromGlUuid === $toGlUuid) {
            throw new InvalidArgumentException('Pick two different accounts.');
        }
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $from = $this->assetAccount($business, $fromGlUuid);
        $to = $this->assetAccount($business, $toGlUuid);

        return $this->ledger->createManualEntry(
            $business,
            $date !== null && $date !== '' ? $date : now()->toDateString(),
            $memo !== null && trim($memo) !== '' ? trim($memo) : 'Funds transfer',
            [
                ['gl_account_id' => $to->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Transfer in from '.$from->name],
                ['gl_account_id' => $from->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Transfer out to '.$to->name],
            ],
        );
    }

    /**
     * Record a deposit / top-up into an account. Dr account / Cr Owner contributions.
     */
    public function deposit(
        Business $business,
        string $toGlUuid,
        float $amount,
        ?string $date,
        ?string $memo,
    ): JournalEntry {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }
        $this->ensureEquityAccounts($business);

        $to = $this->assetAccount($business, $toGlUuid);
        $contrib = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('code', self::CODE_OWNER_CONTRIBUTIONS)
            ->firstOrFail();

        return $this->ledger->createManualEntry(
            $business,
            $date !== null && $date !== '' ? $date : now()->toDateString(),
            $memo !== null && trim($memo) !== '' ? trim($memo) : 'Funds deposit',
            [
                ['gl_account_id' => $to->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Deposit to '.$to->name],
                ['gl_account_id' => $contrib->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Owner contributions'],
            ],
        );
    }

    /**
     * Record a withdrawal / cash-out. Dr Owner drawings / Cr account.
     */
    public function withdraw(
        Business $business,
        string $fromGlUuid,
        float $amount,
        ?string $date,
        ?string $memo,
    ): JournalEntry {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }
        $this->ensureEquityAccounts($business);

        $from = $this->assetAccount($business, $fromGlUuid);
        $drawings = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('code', self::CODE_OWNER_DRAWINGS)
            ->firstOrFail();

        return $this->ledger->createManualEntry(
            $business,
            $date !== null && $date !== '' ? $date : now()->toDateString(),
            $memo !== null && trim($memo) !== '' ? trim($memo) : 'Funds withdrawal',
            [
                ['gl_account_id' => $drawings->id, 'debit' => $amount, 'credit' => 0, 'description' => 'Owner drawings'],
                ['gl_account_id' => $from->id, 'debit' => 0, 'credit' => $amount, 'description' => 'Withdrawal from '.$from->name],
            ],
        );
    }

    private function assetAccount(Business $business, string $glUuid): GlAccount
    {
        $gl = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('uuid', $glUuid)
            ->first();
        if ($gl === null) {
            throw new InvalidArgumentException('Pick a valid account.');
        }
        if ($gl->type !== 'asset') {
            throw new InvalidArgumentException('Only cash or bank (asset) accounts can hold funds.');
        }

        return $gl;
    }

    private function glBalance(Business $business, int $glAccountId): float
    {
        $row = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.business_id', $business->id)
            ->where('journal_lines.gl_account_id', $glAccountId)
            ->selectRaw('COALESCE(SUM(debit), 0) as dr, COALESCE(SUM(credit), 0) as cr')
            ->first();

        $dr = (float) ($row?->dr ?? 0);
        $cr = (float) ($row?->cr ?? 0);

        return round($dr - $cr, 2);
    }
}
