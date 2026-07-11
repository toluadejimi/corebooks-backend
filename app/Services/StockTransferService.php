<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StockTransferService
{
    /**
     * Move stock from one branch (location) to another using FIFO batches at source.
     */
    public function transfer(
        Business $business,
        int $userId,
        string $fromLocationUuid,
        string $toLocationUuid,
        string $productUuid,
        float $qty,
    ): array {
        return $this->transferMany($business, $userId, $fromLocationUuid, $toLocationUuid, [
            ['product_uuid' => $productUuid, 'qty' => $qty],
        ]);
    }

    /**
     * Move one or more products between branches in a single transaction (shared transfer_uuid).
     *
     * @param  list<array{product_uuid: string, qty: float|int|string}>  $lines
     */
    public function transferMany(
        Business $business,
        int $userId,
        string $fromLocationUuid,
        string $toLocationUuid,
        array $lines,
    ): array {
        if ($fromLocationUuid === $toLocationUuid) {
            throw new InvalidArgumentException('Source and destination branch must differ.');
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Add at least one product line.');
        }

        return DB::transaction(function () use ($business, $fromLocationUuid, $toLocationUuid, $lines) {
            $from = $business->locations()->where('uuid', $fromLocationUuid)->lockForUpdate()->firstOrFail();
            $to = $business->locations()->where('uuid', $toLocationUuid)->lockForUpdate()->firstOrFail();
            $refUuid = (string) Str::uuid();
            $movedLines = [];
            $totalQty = 0.0;
            $totalCost = 0.0;

            foreach ($lines as $line) {
                $productUuid = (string) ($line['product_uuid'] ?? '');
                $qty = (float) ($line['qty'] ?? 0);
                if ($productUuid === '' || $qty <= 0) {
                    throw new InvalidArgumentException('Each line needs a product and a positive quantity.');
                }

                $product = Product::query()
                    ->where('business_id', $business->id)
                    ->where('uuid', $productUuid)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lineResult = $this->moveProductFifo(
                    $business,
                    $product,
                    $from,
                    $to,
                    $qty,
                    $refUuid,
                );

                $movedLines[] = $lineResult;
                $totalQty += $lineResult['qty'];
                $totalCost += $lineResult['amount'];
            }

            return [
                'transfer_uuid' => $refUuid,
                'from_location_uuid' => $from->uuid,
                'to_location_uuid' => $to->uuid,
                'qty' => round($totalQty, 3),
                'amount' => round($totalCost, 2),
                'lines' => $movedLines,
            ];
        });
    }

    /**
     * @return array{product_uuid: string, qty: float, amount: float}
     */
    private function moveProductFifo(
        Business $business,
        Product $product,
        Location $from,
        Location $to,
        float $qty,
        string $refUuid,
    ): array {
        $remaining = $qty;
        $moved = 0.0;
        $amount = 0.0;

        $batches = ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('product_id', $product->id)
            ->where('location_id', $from->id)
            ->where('qty', '>', 0)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $batch->qty;
            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            $unitCost = (float) $batch->cost_price_snapshot;
            $batch->qty = round($available - $take, 3);
            $batch->version = (int) $batch->version + 1;
            $batch->save();

            StockMovement::query()->create([
                'business_id' => $business->id,
                'product_id' => $product->id,
                'product_batch_id' => $batch->id,
                'location_id' => $from->id,
                'uuid' => (string) Str::uuid(),
                'type' => 'transfer_out',
                'qty' => -1 * abs($take),
                'ref_type' => 'stock_transfer',
                'ref_uuid' => $refUuid,
                'version' => 1,
            ]);

            $destBatch = $this->resolveOrCreateDestinationBatch(
                $business,
                $product,
                $to->id,
                $unitCost,
                $batch->expiry_date,
            );

            $destBatch->qty = round((float) $destBatch->qty + $take, 3);
            $destBatch->version = (int) $destBatch->version + 1;
            $destBatch->save();

            StockMovement::query()->create([
                'business_id' => $business->id,
                'product_id' => $product->id,
                'product_batch_id' => $destBatch->id,
                'location_id' => $to->id,
                'uuid' => (string) Str::uuid(),
                'type' => 'transfer_in',
                'qty' => abs($take),
                'ref_type' => 'stock_transfer',
                'ref_uuid' => $refUuid,
                'version' => 1,
            ]);

            $remaining -= $take;
            $moved += $take;
            $amount += $take * $unitCost;
        }

        if ($remaining > 0.0001) {
            throw new InvalidArgumentException('Insufficient stock at source branch for '.$product->name.'.');
        }

        return [
            'product_uuid' => $product->uuid,
            'qty' => round($moved, 3),
            'amount' => round($amount, 2),
        ];
    }

    private function resolveOrCreateDestinationBatch(
        Business $business,
        Product $product,
        int $toLocationId,
        float $costSnapshot,
        ?\DateTimeInterface $expiry,
    ): ProductBatch {
        $q = ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('product_id', $product->id)
            ->where('location_id', $toLocationId)
            ->where('cost_price_snapshot', $costSnapshot);

        if ($expiry === null) {
            $q->whereNull('expiry_date');
        } else {
            $q->whereDate('expiry_date', $expiry);
        }

        $existing = $q->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        return ProductBatch::query()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'location_id' => $toLocationId,
            'uuid' => (string) Str::uuid(),
            'qty' => 0,
            'expiry_date' => $expiry,
            'cost_price_snapshot' => $costSnapshot,
            'version' => 1,
        ]);
    }
}
