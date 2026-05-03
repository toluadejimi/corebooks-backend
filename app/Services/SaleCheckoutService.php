<?php

namespace App\Services;

use App\Models\Business;
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
    ): Sale {
        return DB::transaction(function () use ($business, $userId, $locationUuid, $lines, $payments, $idempotencyKey, $discountTotal) {
            if ($idempotencyKey) {
                $existing = Sale::query()
                    ->where('business_id', $business->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing->load(['lines.product', 'payments']);
                }
            }

            $location = $business->locations()->where('uuid', $locationUuid)->firstOrFail();

            $sale = Sale::query()->create([
                'business_id' => $business->id,
                'location_id' => $location->id,
                'user_id' => $userId,
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
            foreach ($payments as $p) {
                $amount = (float) $p['amount'];
                $paymentSum += $amount;
                Payment::query()->create([
                    'business_id' => $business->id,
                    'sale_id' => $sale->id,
                    'uuid' => (string) Str::uuid(),
                    'method' => $p['method'],
                    'amount' => $amount,
                    'meta' => $p['meta'] ?? null,
                ]);
            }

            // Compare with a small tolerance: client + JSON float rounding can drift vs PHP accumulators.
            if (abs(round($paymentSum, 2) - $grandTotal) > 0.02) {
                throw new InvalidArgumentException('Payment total must match grand total.');
            }

            $sale = $sale->fresh(['lines.product', 'payments']);
            $this->ledger->postSaleJournal($business, $sale);

            return $sale;
        });
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
