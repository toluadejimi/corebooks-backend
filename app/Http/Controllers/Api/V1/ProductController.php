<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 50), 1), 500);

        $query = Product::query()->where('business_id', $business->id);

        $locUuid = $request->query('location_uuid');
        $locationId = null;
        if (is_string($locUuid) && $locUuid !== '') {
            $locationId = $business->locations()->where('uuid', $locUuid)->value('id');
        }

        if ($locationId !== null) {
            $query->withSum([
                'batches as batches_sum_qty' => static fn ($bq) => $bq->where('location_id', $locationId),
            ], 'qty');
        } else {
            $query->withSum('batches', 'qty');
        }

        $search = $request->query('search');
        if (is_string($search) && $search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('barcode', 'like', $term);
            });
        }

        $products = $query->orderBy('name')->paginate($perPage);

        $products->setCollection(
            $products->getCollection()->map(fn (Product $p) => $this->productResponse($p))
        );

        return response()->json($products);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:128'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'track_batches' => ['boolean'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'initial_qty' => ['nullable', 'numeric', 'min:0'],
            'location_uuid' => ['nullable', 'uuid'],
            'expiry_date' => ['nullable', 'date'],
            'category_uuid' => ['nullable', 'uuid'],
        ]);

        $location = $business->locations()->where('is_default', true)->first()
            ?? $business->locations()->firstOrFail();

        if (! empty($data['location_uuid'])) {
            $location = $business->locations()->where('uuid', $data['location_uuid'])->firstOrFail();
        }

        $categoryId = $this->resolveCategoryId($business, $data['category_uuid'] ?? null);

        $product = Product::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'category_id' => $categoryId,
            'name' => $data['name'],
            'image_url' => $data['image_url'] ?? null,
            'sku' => $data['sku'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'cost_price' => $data['cost_price'] ?? 0,
            'selling_price' => $data['selling_price'] ?? 0,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 0,
            'track_batches' => $data['track_batches'] ?? false,
            'vat_rate' => $data['vat_rate'] ?? $business->default_vat_rate,
            'version' => 1,
        ]);

        $qty = (float) ($data['initial_qty'] ?? 0);
        ProductBatch::query()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'uuid' => (string) Str::uuid(),
            'qty' => $qty,
            'expiry_date' => $data['expiry_date'] ?? null,
            'cost_price_snapshot' => $product->cost_price,
            'version' => 1,
        ]);

        return response()->json(['data' => $this->productResponse($product->fresh())], 201);
    }

    public function show(Business $business, Product $product): JsonResponse
    {
        $this->assertProduct($business, $product);

        return response()->json(['data' => $this->productResponse($product)]);
    }

    public function update(Request $request, Business $business, Product $product): JsonResponse
    {
        $this->assertProduct($business, $product);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:128'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'track_batches' => ['sometimes', 'boolean'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'client_version' => ['sometimes', 'integer', 'min:1'],
            'category_uuid' => ['sometimes', 'nullable', 'uuid'],
        ]);

        if (isset($data['client_version']) && (int) $data['client_version'] !== (int) $product->version) {
            return response()->json([
                'message' => 'Version conflict',
                'server' => $this->productResponse($product),
            ], 409);
        }

        $product->fill(collect($data)->except(['client_version', 'category_uuid'])->toArray());
        if (array_key_exists('category_uuid', $data)) {
            $product->category_id = $this->resolveCategoryId($business, $data['category_uuid']);
        }
        $product->version = $product->version + 1;
        $product->save();

        return response()->json(['data' => $this->productResponse($product)]);
    }

    public function destroy(Business $business, Product $product): JsonResponse
    {
        $this->assertProduct($business, $product);
        $product->delete();

        return response()->json(['ok' => true]);
    }

    private function assertProduct(Business $business, Product $product): void
    {
        abort_if($product->business_id !== $business->id, 404);
    }

    private function resolveCategoryId(Business $business, ?string $categoryUuid): ?int
    {
        if ($categoryUuid === null || $categoryUuid === '') {
            return null;
        }

        $id = Category::query()
            ->where('business_id', $business->id)
            ->where('uuid', $categoryUuid)
            ->value('id');

        abort_if($id === null, 422, 'Unknown category for this business.');

        return (int) $id;
    }

    private function productResponse(Product $product): array
    {
        $product->load(['category:id,uuid,name']);
        $product->loadSum('batches', 'qty');

        return [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'image_url' => $product->image_url,
            'category_uuid' => $product->category?->uuid,
            'category_name' => $product->category?->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'cost_price' => (float) $product->cost_price,
            'selling_price' => (float) $product->selling_price,
            'low_stock_threshold' => (int) $product->low_stock_threshold,
            'track_batches' => (bool) $product->track_batches,
            'vat_rate' => $product->vat_rate !== null ? (float) $product->vat_rate : null,
            'stock_qty' => (float) ($product->batches_sum_qty ?? 0),
            'version' => (int) $product->version,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }
}
