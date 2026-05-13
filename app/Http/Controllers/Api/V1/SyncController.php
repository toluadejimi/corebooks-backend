<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
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
                            'available_online' => (bool) $product->available_online,
                            'gallery_urls' => $product->gallery_urls ?? [],
                            'variations' => $product->variations ?? [],
                            'selling_price' => (float) $product->selling_price,
                            'updated_at' => $product->updated_at?->toIso8601String(),
                        ],
                    ];

                    continue;
                }

                $payload = $op['payload'];
                $gallery = $payload['gallery_urls'] ?? null;
                $imageUrl = $payload['image_url'] ?? null;
                if (is_array($gallery) && count($gallery) > 0) {
                    if ($imageUrl === null || $imageUrl === '') {
                        $imageUrl = $gallery[0];
                    }
                }

                $categoryId = false;
                if (array_key_exists('category_uuid', $payload)) {
                    $cu = $payload['category_uuid'];
                    if ($cu === null || $cu === '') {
                        $categoryId = null;
                    } else {
                        $resolved = Category::query()
                            ->where('business_id', $business->id)
                            ->where('uuid', $cu)
                            ->value('id');
                        if ($resolved !== null) {
                            $categoryId = (int) $resolved;
                        } elseif ($product === null) {
                            $categoryId = null;
                        }
                    }
                }

                $attrs = [
                    'name' => $payload['name'] ?? 'Product',
                    'image_url' => $imageUrl,
                    'available_online' => (bool) ($payload['available_online'] ?? false),
                    'gallery_urls' => is_array($gallery) ? array_values($gallery) : null,
                    'variations' => isset($payload['variations']) && is_array($payload['variations'])
                        ? array_values($payload['variations'])
                        : null,
                    'sku' => $payload['sku'] ?? null,
                    'barcode' => $payload['barcode'] ?? null,
                    'cost_price' => $payload['cost_price'] ?? 0,
                    'selling_price' => $payload['selling_price'] ?? 0,
                    'low_stock_threshold' => $payload['low_stock_threshold'] ?? 0,
                    'track_batches' => $payload['track_batches'] ?? false,
                    'vat_rate' => $payload['vat_rate'] ?? $business->default_vat_rate,
                    'version' => ($product?->version ?? 0) + 1,
                ];
                if ($categoryId !== false) {
                    $attrs['category_id'] = $categoryId;
                }

                Product::query()->updateOrCreate(
                    [
                        'business_id' => $business->id,
                        'uuid' => $payload['uuid'] ?? $op['uuid'],
                    ],
                    $attrs,
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
            $isFullSnapshot = empty($since);
            if ($since) {
                $q->where('updated_at', '>', $since);
            }
            // Full snapshots return the entire catalogue so the client can
            // reconcile deletions; incremental pulls stay paged at 1000.
            if (! $isFullSnapshot) {
                $q->limit(1000);
            }
            $changes['products'] = $q->orderBy('updated_at')->get()->map(function (Product $p) use ($locationId) {
                $stock = $locationId
                    ? (float) ($p->location_stock_qty ?? 0)
                    : (float) ($p->batches_sum_qty ?? 0);

                return [
                    'uuid' => $p->uuid,
                    'name' => $p->name,
                    'image_url' => $p->image_url,
                    'available_online' => (bool) $p->available_online,
                    'gallery_urls' => $p->gallery_urls ?? [],
                    'variations' => $p->variations ?? [],
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
            'products_full_snapshot' => isset($isFullSnapshot) ? $isFullSnapshot : false,
        ]);
    }
}
