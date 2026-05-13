<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PurchaseReceiveService
{
    /**
     * Record a received purchase into stock at a branch (creates PO + lines as received).
     *
     * @param  array<int, array{product_uuid: string, qty: float, unit_cost: float, expiry_date?: ?string}>  $lines
     */
    public function receive(
        Business $business,
        string $locationUuid,
        array $lines,
        ?string $supplierUuid,
        ?string $supplierName,
        ?string $supplierPhone,
        ?string $orderedAt = null,
    ): PurchaseOrder {
        if ($lines === []) {
            throw new InvalidArgumentException('At least one line is required.');
        }

        return DB::transaction(function () use ($business, $locationUuid, $lines, $supplierUuid, $supplierName, $supplierPhone, $orderedAt) {
            $location = $business->locations()->where('uuid', $locationUuid)->lockForUpdate()->firstOrFail();

            $supplier = $this->resolveSupplier($business, $supplierUuid, $supplierName, $supplierPhone);

            $total = 0.0;
            foreach ($lines as $line) {
                $q = (float) ($line['qty'] ?? 0);
                $c = (float) ($line['unit_cost'] ?? 0);
                if ($q <= 0) {
                    throw new InvalidArgumentException('Each line needs a positive quantity.');
                }
                $total += round($q * $c, 2);
            }

            $orderedAtTs = $orderedAt !== null && trim($orderedAt) !== ''
                ? \Carbon\Carbon::parse($orderedAt)
                : now();

            $po = PurchaseOrder::query()->create([
                'business_id' => $business->id,
                'supplier_id' => $supplier->id,
                'location_id' => $location->id,
                'uuid' => (string) Str::uuid(),
                'status' => 'received',
                'total' => round($total, 2),
                'ordered_at' => $orderedAtTs,
                'version' => 1,
            ]);

            foreach ($lines as $line) {
                $product = Product::query()
                    ->where('business_id', $business->id)
                    ->where('uuid', $line['product_uuid'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (float) $line['qty'];
                $unitCost = (float) $line['unit_cost'];
                $expiry = isset($line['expiry_date']) && $line['expiry_date'] !== null && $line['expiry_date'] !== ''
                    ? \Carbon\Carbon::parse($line['expiry_date'])->toDateString()
                    : null;

                $batch = ProductBatch::query()->create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'uuid' => (string) Str::uuid(),
                    'qty' => round($qty, 3),
                    'expiry_date' => $expiry,
                    'cost_price_snapshot' => round($unitCost, 2),
                    'version' => 1,
                ]);

                PurchaseOrderLine::query()->create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $product->id,
                    'product_batch_id' => $batch->id,
                    'qty' => round($qty, 3),
                    'unit_cost' => round($unitCost, 2),
                    'expiry_date' => $expiry,
                ]);

                StockMovement::query()->create([
                    'business_id' => $business->id,
                    'product_id' => $product->id,
                    'product_batch_id' => $batch->id,
                    'location_id' => $location->id,
                    'uuid' => (string) Str::uuid(),
                    'type' => 'purchase_in',
                    'qty' => abs($qty),
                    'ref_type' => 'purchase_order',
                    'ref_uuid' => $po->uuid,
                    'version' => 1,
                ]);

                $product->cost_price = round($unitCost, 2);
                $product->version = (int) $product->version + 1;
                $product->save();
            }

            $ledgerSupplier = Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            $ledgerSupplier->balance = round((float) $ledgerSupplier->balance + (float) $po->total, 2);
            $ledgerSupplier->version = (int) $ledgerSupplier->version + 1;
            $ledgerSupplier->save();

            return $po->fresh(['lines.product', 'supplier', 'location']);
        });
    }

    private function resolveSupplier(
        Business $business,
        ?string $supplierUuid,
        ?string $supplierName,
        ?string $supplierPhone,
    ): Supplier {
        if ($supplierUuid) {
            return Supplier::query()
                ->where('business_id', $business->id)
                ->where('uuid', $supplierUuid)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $name = trim((string) $supplierName);
        if ($name === '') {
            throw new InvalidArgumentException('Provide supplier_uuid or supplier_name.');
        }

        return Supplier::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'phone' => $supplierPhone ? trim($supplierPhone) : null,
            'balance' => 0,
            'version' => 1,
        ]);
    }
}
