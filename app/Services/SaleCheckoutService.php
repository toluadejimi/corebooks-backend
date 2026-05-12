<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerCreditEntry;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SaleCheckoutService
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function checkout(
        Business $business,
        int $userId,
        string $locationUuid,
        array $lines,
        array $payments,
        ?string $idempotencyKey,
        float $discountTotal = 0,
        ?string $customerUuid = null,
    ): Sale {
        return DB::transaction(function () use ($business, $userId, $locationUuid, $lines, $payments, $idempotencyKey, $discountTotal, $customerUuid) {
            if ($idempotencyKey) {
                $existing = Sale::query()
                    ->where('business_id', $business->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing->load(['lines.product', 'payments', 'customer']);
                }
            }

            $location = $business->locations()->where('uuid', $locationUuid)->firstOrFail();
            $customer = $this->resolveCustomer($business, $customerUuid);

            $sale = Sale::query()->create([
                'business_id' => $business->id,
                'location_id' => $location->id,
                'user_id' => $userId,
                'customer_id' => $customer?->id,
                'uuid' => (string) Str::uuid(),
                'receipt_no' => $this->nextReceiptNo($business),
                'status' => 'completed',
                'idempotency_key' => $idempotencyKey,
                'discount_total' => $discountTotal,
                'version' => 1,
                'sold_at' => now(),
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($lines as $line) {
                $product = Product::query()
                    ->where('business_id', $business->id)
                    ->where('uuid', $line['product_uuid'])
                    ->firstOrFail();

                $qty = (float) $line['qty'];
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Quantity must be positive.');
                }

                $unitPrice = (float) ($line['unit_price'] ?? $product->selling_price);
                $taxRate = (float) ($line['tax_rate'] ?? $product->vat_rate ?? $business->default_vat_rate);

                $lineSubtotal = round($qty * $unitPrice, 2);
                $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
                $lineTotal = $lineSubtotal + $lineTax;

                $batch = $this->resolveBatch($business, $product, $location->id, $line['batch_uuid'] ?? null, $qty);

                SaleLine::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_batch_id' => $batch->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
                ]);

                $batch->qty = (float) $batch->qty - $qty;
                if ($batch->qty < 0) {
                    throw new InvalidArgumentException('Insufficient stock for '.$product->name);
                }
                $batch->version = $batch->version + 1;
                $batch->save();

                StockMovement::query()->create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'product_batch_id' => $batch->id,
                    'location_id' => $location->id,
                    'uuid' => (string) Str::uuid(),
                    'type' => 'out',
                    'qty' => -1 * abs($qty),
                    'ref_type' => 'sale',
                    'ref_uuid' => $sale->uuid,
                    'version' => 1,
                ]);

                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;
            }

            $grandTotal = round($subtotal + $taxTotal - $discountTotal, 2);

            $sale->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
            ]);

            $paymentSum = 0.0;
            $creditTotal = 0.0;
            $creditPaymentRecords = [];
            foreach ($payments as $p) {
                $amount = (float) $p['amount'];
                $method = $p['method'];
                $paymentSum += $amount;
                if ($method === 'credit') {
                    $creditTotal += $amount;
                }
                $payment = Payment::query()->create([
                    'business_id' => $business->id,
                    'sale_id' => $sale->id,
                    'uuid' => (string) Str::uuid(),
                    'method' => $method,
                    'amount' => $amount,
                    'meta' => $p['meta'] ?? null,
                ]);
                if ($method === 'credit') {
                    $creditPaymentRecords[] = $payment;
                }
            }

            // Compare with a small tolerance: client + JSON float rounding can drift vs PHP accumulators.
            if (abs(round($paymentSum, 2) - $grandTotal) > 0.02) {
                throw new InvalidArgumentException('Payment total must match grand total.');
            }

            if ($creditTotal > 0) {
                if ($customer === null || $customer->is_walk_in) {
                    throw new InvalidArgumentException('Pick a saved customer (not Walk-in) to sell on credit.');
                }
                if (! $customer->credit_enabled) {
                    throw new InvalidArgumentException('Credit is not enabled for this customer.');
                }
                $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->first();
                $newBalance = round((float) $locked->credit_balance + $creditTotal, 2);
                $limit = (float) $locked->credit_limit;
                if ($limit > 0 && $newBalance > round($limit + 0.0001, 2)) {
                    throw new InvalidArgumentException(
                        'Credit limit reached. Outstanding will become '.number_format($newBalance, 2)
                        .' but the limit is '.number_format($limit, 2).'.'
                    );
                }
                $locked->credit_balance = $newBalance;
                $locked->save();

                foreach ($creditPaymentRecords as $payment) {
                    CustomerCreditEntry::query()->create([
                        'business_id' => $business->id,
                        'customer_id' => $locked->id,
                        'sale_id' => $sale->id,
                        'payment_id' => $payment->id,
                        'user_id' => $userId,
                        'uuid' => (string) Str::uuid(),
                        'type' => 'charge',
                        'method' => 'credit',
                        'amount' => (float) $payment->amount,
                        'balance_after' => $locked->credit_balance,
                        'reference' => $sale->receipt_no,
                        'notes' => 'Sale on credit',
                        'occurred_at' => now(),
                    ]);
                }
            }

            $sale = $sale->fresh(['lines.product', 'payments', 'customer']);
            $this->ledger->postSaleJournal($business, $sale);

            return $sale;
        });
    }

    /**
     * Returns the requested customer or — if none / unknown — the auto-seeded Walk-in customer.
     */
    private function resolveCustomer(Business $business, ?string $customerUuid): ?Customer
    {
        if ($customerUuid !== null && $customerUuid !== '') {
            $customer = Customer::query()
                ->where('business_id', $business->id)
                ->where('uuid', $customerUuid)
                ->first();
            if ($customer) {
                return $customer;
            }
        }

        $walkIn = Customer::query()
            ->where('business_id', $business->id)
            ->where('is_walk_in', true)
            ->first();
        if ($walkIn) {
            return $walkIn;
        }

        return Customer::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Walk-in customer',
            'is_walk_in' => true,
            'version' => 1,
        ]);
    }

    private function nextReceiptNo(Business $business): string
    {
        return 'RCP-'.$business->id.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
    }

    private function resolveBatch(
        Business $business,
        Product $product,
        int $locationId,
        ?string $batchUuid,
        float $qty,
    ): ProductBatch {
        $query = ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('product_id', $product->id)
            ->where('location_id', $locationId)
            ->where('qty', '>', 0)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC');

        if ($batchUuid) {
            return (clone $query)->where('uuid', $batchUuid)->lockForUpdate()->firstOrFail();
        }

        $batch = $query->lockForUpdate()->first();
        if (! $batch) {
            throw new InvalidArgumentException('No stock batch available for '.$product->name);
        }

        if ((float) $batch->qty < $qty) {
            throw new InvalidArgumentException('Insufficient stock on batch for '.$product->name);
        }

        return $batch;
    }
}
