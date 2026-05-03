<?php

namespace App\Services;

use App\Models\Business;
use App\Models\TokenTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BusinessTokenService
{
    /**
     * Debit tokens after a successful paid action. Caller must only invoke when the action succeeded.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function debit(Business $business, ?User $user, string $reason, int $amount, ?array $meta = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($business, $user, $reason, $amount, $meta): void {
            /** @var Business $locked */
            $locked = Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $balance = (int) $locked->token_balance;
            if ($balance < $amount) {
                throw new RuntimeException('INSUFFICIENT_TOKENS');
            }
            $newBalance = $balance - $amount;
            $locked->token_balance = $newBalance;
            $locked->save();

            TokenTransaction::query()->create([
                'business_id' => $locked->id,
                'user_id' => $user?->id,
                'reason' => $reason,
                'amount' => -$amount,
                'balance_after' => $newBalance,
                'meta' => $meta,
            ]);
        });
    }

    /**
     * Credit tokens (e.g. admin top-up). Amount must be positive.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function credit(Business $business, ?User $user, string $reason, int $amount, ?array $meta = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($business, $user, $reason, $amount, $meta): void {
            /** @var Business $locked */
            $locked = Business::query()->whereKey($business->id)->lockForUpdate()->firstOrFail();
            $newBalance = (int) $locked->token_balance + $amount;
            $locked->token_balance = $newBalance;
            $locked->save();

            TokenTransaction::query()->create([
                'business_id' => $locked->id,
                'user_id' => $user?->id,
                'reason' => $reason,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'meta' => $meta,
            ]);
        });
    }
}
