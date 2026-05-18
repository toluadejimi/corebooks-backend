<?php

namespace App\Services;

use App\Models\Business;
use App\Models\GlAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resolves mobile/web "funds account" UUIDs to GL accounts and checks spendable balance.
 */
final class FundAccountService
{
    public function __construct(
        private readonly AccountFundsService $funds,
    ) {}

    public function resolveGlAccount(Business $business, ?string $accountUuid): GlAccount
    {
        if ($accountUuid === null || trim($accountUuid) === '') {
            return GlAccount::query()
                ->where('business_id', $business->id)
                ->where('code', GeneralLedgerService::CODE_CASH)
                ->firstOrFail();
        }

        $accounts = $this->funds->listAccounts($business);
        foreach ($accounts as $a) {
            if ($a['uuid'] === $accountUuid || $a['gl_account_uuid'] === $accountUuid) {
                $gl = GlAccount::query()
                    ->where('business_id', $business->id)
                    ->where('uuid', $a['gl_account_uuid'])
                    ->first();
                if ($gl !== null) {
                    return $gl;
                }
            }
        }

        throw ValidationException::withMessages([
            'account_uuid' => 'Pick a valid account.',
        ]);
    }

    public function assertHasFunds(Business $business, GlAccount $gl, float $amount): void
    {
        $balance = $this->balance($business, (int) $gl->id);
        if ($amount > $balance + 0.0001) {
            $remaining = number_format(max($balance, 0), 2, '.', ',');
            $name = $gl->name !== '' ? $gl->name : 'this account';
            throw ValidationException::withMessages([
                'amount' => "Not enough funds in {$name}. Available balance: {$remaining}.",
            ]);
        }
    }

    public function balance(Business $business, int $glAccountId): float
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
