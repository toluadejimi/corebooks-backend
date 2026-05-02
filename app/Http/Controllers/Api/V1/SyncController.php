<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function push(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'operations' => ['required', 'array', 'max:500'],
            'operations.*.uuid' => ['required', 'uuid'],
            'operations.*.entity' => ['required', 'string', 'max:64'],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.client_version' => ['nullable', 'integer', 'min:1'],
        ]);

        $synced = [];
        $conflicts = [];

        foreach ($data['operations'] as $op) {
            if ($op['entity'] === 'product') {
                $product = Product::query()
                    ->where('business_id', $business->id)
                    ->where('uuid', $op['payload']['uuid'] ?? $op['uuid'])
                    ->first();

                if ($product && isset($op['client_version']) && (int) $op['client_version'] !== (int) $product->version) {
                    $conflicts[] = [
                        'uuid' => $product->uuid,
                        'entity' => 'product',
                        'server' => [
                            'version' => (int) $product->version,
                            'name' => $product->name,
                            'image_url' => $product->image_url,
                            'selling_price' => (float) $product->selling_price,
                            'updated_at' => $product->updated_at?->toIso8601String(),
                        ],
                    ];

                    continue;
                }

                Product::query()->updateOrCreate(
                    [
                        'business_id' => $business->id,
                        'uuid' => $op['payload']['uuid'] ?? $op['uuid'],
                    ],
                    [
                        'name' => $op['payload']['name'] ?? 'Product',
                        'image_url' => $op['payload']['image_url'] ?? null,
                        'sku' => $op['payload']['sku'] ?? null,
                        'barcode' => $op['payload']['barcode'] ?? null,
                        'cost_price' => $op['payload']['cost_price'] ?? 0,
                        'selling_price' => $op['payload']['selling_price'] ?? 0,
                        'low_stock_threshold' => $op['payload']['low_stock_threshold'] ?? 0,
                        'track_batches' => $op['payload']['track_batches'] ?? false,
                        'vat_rate' => $op['payload']['vat_rate'] ?? $business->default_vat_rate,
                        'version' => ($product?->version ?? 0) + 1,
                    ],
                );

                $synced[] = $op['uuid'];
            }
        }

        return response()->json([
            'synced' => $synced,
            'conflicts' => $conflicts,
        ]);
    }

    public function pull(Request $request, Business $business): JsonResponse
    {
        $since = $request->query('since');
        $entities = array_filter(explode(',', (string) $request->query('entities', 'products')));
        $locationUuid = $request->query('location_uuid');
        $locationId = null;
        if ($locationUuid) {
            $locationId = $business->locations()->where('uuid', $locationUuid)->value('id');
        }

        $changes = [];

        if (in_array('products', $entities, true)) {
            $q = Product::query()
                ->where('business_id', $business->id)
                ->with(['category:id,uuid,name']);
            if ($locationId) {
                $q->withSum([
                    'batches as location_stock_qty' => fn ($bq) => $bq->where('location_id', $locationId),
                ], 'qty');
            } else {
                $q->withSum('batches', 'qty');
            }
            if ($since) {
                $q->where('updated_at', '>', $since);
            }
            $changes['products'] = $q->orderBy('updated_at')->limit(1000)->get()->map(function (Product $p) use ($locationId) {
                $stock = $locationId
                    ? (float) ($p->location_stock_qty ?? 0)
                    : (float) ($p->batches_sum_qty ?? 0);

                return [
                    'uuid' => $p->uuid,
                    'name' => $p->name,
                    'image_url' => $p->image_url,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode,
                    'category_uuid' => $p->category?->uuid,
                    'category_name' => $p->category?->name,
                    'cost_price' => (float) $p->cost_price,
                    'selling_price' => (float) $p->selling_price,
                    'low_stock_threshold' => (int) $p->low_stock_threshold,
                    'track_batches' => (bool) $p->track_batches,
                    'vat_rate' => $p->vat_rate !== null ? (float) $p->vat_rate : null,
                    'stock_qty' => $stock,
                    'version' => (int) $p->version,
                    'updated_at' => $p->updated_at?->toIso8601String(),
                ];
            });
        }

        return response()->json([
            'changes' => $changes,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
