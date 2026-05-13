<?php

namespace App\Services;

use App\Models\Business;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SalesReturn;
use App\Models\SalesReturnLine;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Restocks one or more lines from a completed sale and records a refund.
 * Restocks back to the original sale's batch (or skips if `restock=false`).
 */
class SalesReturnService
{
    /**
     * @param  array<int, array{sale_line_uuid:string, qty:float, restock?:bool}>  $lines
     */
    public function process(
        Business $business,
        Sale $sale,
        int $userId,
        array $lines,
        ?string $reason,
        string $refundMethod = 'cash',
        ?string $returnedAt = null,
    ): SalesReturn {
        if ($sale->business_id !== $business->id) {
            throw new InvalidArgumentException('Sale does not belong to this business.');
        }

        return DB::transaction(function () use ($business, $sale, $userId, $lines, $reason, $refundMethod, $returnedAt): SalesReturn {
            $sale->loadMissing(['lines', 'returns.lines']);

            $alreadyByLine = [];
            foreach ($sale->returns as $r) {
                foreach ($r->lines as $rl) {
                    $alreadyByLine[$rl->sale_line_id] = ($alreadyByLine[$rl->sale_line_id] ?? 0) + (float) $rl->qty;
                }
            }

            $returnedAtTs = $returnedAt !== null && trim($returnedAt) !== ''
                ? \Carbon\Carbon::parse($returnedAt)
                : now();

            $return = SalesReturn::query()->create([
                'business_id' => $business->id,
                'sale_id' => $sale->id,
                'location_id' => $sale->location_id,
                'user_id' => $userId,
                'customer_id' => $sale->customer_id,
                'uuid' => (string) Str::uuid(),
                'reason' => $reason,
                'refund_method' => $refundMethod,
                'refund_total' => 0,
                'version' => 1,
                'returned_at' => $returnedAtTs,
            ]);

            $refundTotal = 0.0;

            foreach ($lines as $row) {
                $qtyReq = (float) ($row['qty'] ?? 0);
                if ($qtyReq <= 0) {
                    continue;
                }
                $restock = (bool) ($row['restock'] ?? true);

                /** @var SaleLine|null $sl */
                $sl = $sale->lines->firstWhere('uuid', $row['sale_line_uuid'] ?? null);
                if (! $sl) {
                    $byId = SaleLine::query()->where('sale_id', $sale->id)->where('id', (int) ($row['sale_line_id'] ?? 0))->first();
                    if (! $byId) {
                        throw new InvalidArgumentException('Sale line not found on this sale.');
                    }
                    $sl = $byId;
                }

                $alreadyReturned = (float) ($alreadyByLine[$sl->id] ?? 0);
                $remaining = round((float) $sl->qty - $alreadyReturned, 3);
                if ($qtyReq - $remaining > 0.0009) {
                    throw new InvalidArgumentException('Cannot return more than was sold for line.');
                }

                $unitPrice = (float) $sl->unit_price;
                $taxRate = (float) $sl->tax_rate;
                $lineSub = round($qtyReq * $unitPrice, 2);
                $lineRefund = round($lineSub + round($lineSub * ($taxRate / 100), 2), 2);

                SalesReturnLine::query()->create([
                    'sales_return_id' => $return->id,
                    'sale_line_id' => $sl->id,
                    'product_id' => $sl->product_id,
                    'product_batch_id' => $sl->product_batch_id,
                    'qty' => $qtyReq,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'refund_amount' => $lineRefund,
                    'restock' => $restock,
                ]);

                $refundTotal += $lineRefund;

                if ($restock && $sl->product_batch_id !== null) {
                    /** @var ProductBatch|null $batch */
                    $batch = ProductBatch::query()->where('id', $sl->product_batch_id)->lockForUpdate()->first();
                    if ($batch) {
                        $batch->qty = (float) $batch->qty + $qtyReq;
                        $batch->version = $batch->version + 1;
                        $batch->save();
                    }

                    StockMovement::query()->create([
                        'business_id' => $business->id,
                        'product_id' => $sl->product_id,
                        'product_batch_id' => $sl->product_batch_id,
                        'location_id' => $sale->location_id,
                        'uuid' => (string) Str::uuid(),
                        'type' => 'return_in',
                        'qty' => $qtyReq,
                        'ref_type' => 'sales_return',
                        'ref_uuid' => $return->uuid,
                        'version' => 1,
                    ]);
                }
            }

            $return->refund_total = round($refundTotal, 2);
            $return->save();

            // Mark the parent sale "partially_returned" / "returned" for at-a-glance status.
            $totalSoldQty = (float) $sale->lines->sum(fn (SaleLine $l) => (float) $l->qty);
            $totalReturnedQty = 0.0;
            foreach ($sale->fresh(['returns.lines'])->returns as $r) {
                foreach ($r->lines as $rl) {
                    $totalReturnedQty += (float) $rl->qty;
                }
            }
            if ($totalReturnedQty <= 0) {
                // no-op
            } elseif (abs($totalReturnedQty - $totalSoldQty) < 0.0009) {
                $sale->update(['status' => 'returned']);
            } else {
                $sale->update(['status' => 'partially_returned']);
            }

            return $return->fresh(['lines.product']);
        });
    }
}
