<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerCreditEntry;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Void a completed sale: restock from sale movements, reverse credit charges,
 * remove the sale journal, and mark the sale voided (audit trail kept).
 */
class SaleVoidService
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function void(Business $business, Sale $sale): Sale
    {
        if ((int) $sale->business_id !== (int) $business->id) {
            throw new InvalidArgumentException('Sale does not belong to this business.');
        }

        return DB::transaction(function () use ($business, $sale) {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'voided') {
                throw new InvalidArgumentException('This sale is already voided.');
            }

            if (in_array($locked->status, ['returned', 'partially_returned'], true)) {
                throw new InvalidArgumentException('Cannot void a sale that already has returns. Reverse the returns first, or leave it as returned.');
            }

            if ($locked->status !== 'completed') {
                throw new InvalidArgumentException('Only completed sales can be voided.');
            }

            $this->restockFromMovements($business, $locked);
            $this->reverseCreditCharges($business, $locked);
            $this->ledger->voidBySource($business, 'sale', $locked->uuid);

            $locked->status = 'voided';
            $locked->version = (int) $locked->version + 1;
            $locked->save();

            return $locked->fresh(['lines.product', 'payments', 'customer', 'location', 'user']);
        });
    }

    /**
     * Change walk-in / customer on a non-credit sale (no payment/stock/GL impact).
     */
    public function updateCustomer(Business $business, Sale $sale, ?string $customerUuid): Sale
    {
        if ((int) $sale->business_id !== (int) $business->id) {
            throw new InvalidArgumentException('Sale does not belong to this business.');
        }

        return DB::transaction(function () use ($business, $sale, $customerUuid) {
            /** @var Sale $locked */
            $locked = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, ['voided', 'returned'], true)) {
                throw new InvalidArgumentException('Cannot edit customer on a voided or fully returned sale.');
            }

            $hasCredit = $locked->payments()->where('method', 'credit')->exists();
            if ($hasCredit) {
                throw new InvalidArgumentException('This sale used customer credit. Void it and re-sell if the customer must change.');
            }

            $customerId = null;
            if ($customerUuid !== null && trim($customerUuid) !== '') {
                $customer = Customer::query()
                    ->where('business_id', $business->id)
                    ->where('uuid', $customerUuid)
                    ->first();
                if ($customer === null) {
                    throw new InvalidArgumentException('Customer not found.');
                }
                $customerId = $customer->id;
            }

            $locked->customer_id = $customerId;
            $locked->version = (int) $locked->version + 1;
            $locked->save();

            return $locked->fresh(['lines.product', 'payments', 'customer', 'location', 'user']);
        });
    }

    private function restockFromMovements(Business $business, Sale $sale): void
    {
        $movements = StockMovement::query()
            ->where('business_id', $business->id)
            ->where('ref_type', 'sale')
            ->where('ref_uuid', $sale->uuid)
            ->where('type', 'out')
            ->lockForUpdate()
            ->get();

        foreach ($movements as $movement) {
            $qtyOut = abs((float) $movement->qty);
            if ($qtyOut < 0.0001 || $movement->product_batch_id === null) {
                continue;
            }

            /** @var ProductBatch|null $batch */
            $batch = ProductBatch::query()
                ->where('business_id', $business->id)
                ->whereKey($movement->product_batch_id)
                ->lockForUpdate()
                ->first();

            if ($batch === null) {
                continue;
            }

            $batch->qty = round((float) $batch->qty + $qtyOut, 3);
            $batch->version = (int) $batch->version + 1;
            $batch->save();

            StockMovement::query()->create([
                'business_id' => $business->id,
                'product_id' => $movement->product_id,
                'product_batch_id' => $batch->id,
                'location_id' => $movement->location_id,
                'uuid' => (string) Str::uuid(),
                'type' => 'return_in',
                'qty' => $qtyOut,
                'ref_type' => 'sale_void',
                'ref_uuid' => $sale->uuid,
                'version' => 1,
            ]);
        }
    }

    private function reverseCreditCharges(Business $business, Sale $sale): void
    {
        $charges = CustomerCreditEntry::query()
            ->where('business_id', $business->id)
            ->where('sale_id', $sale->id)
            ->where('type', 'charge')
            ->lockForUpdate()
            ->get();

        if ($charges->isEmpty()) {
            return;
        }

        $byCustomer = $charges->groupBy('customer_id');
        foreach ($byCustomer as $customerId => $entries) {
            $amount = round((float) $entries->sum('amount'), 2);
            if ($amount < 0.01) {
                continue;
            }

            /** @var Customer $customer */
            $customer = Customer::query()->whereKey($customerId)->lockForUpdate()->firstOrFail();
            $customer->credit_balance = round(max(0, (float) $customer->credit_balance - $amount), 2);
            $customer->save();

            CustomerCreditEntry::query()->create([
                'business_id' => $business->id,
                'customer_id' => $customer->id,
                'sale_id' => $sale->id,
                'payment_id' => null,
                'user_id' => $sale->user_id,
                'uuid' => (string) Str::uuid(),
                'type' => 'payment',
                'method' => 'void',
                'amount' => $amount,
                'balance_after' => $customer->credit_balance,
                'reference' => $sale->receipt_no,
                'notes' => 'Sale voided — credit charge reversed',
                'occurred_at' => now(),
            ]);
        }
    }
}
