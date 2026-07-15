<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockWebController extends Controller
{
    use ResolvesWorkspace;

    private const LOW_STOCK_THRESHOLD = 5;

    public function index(Request $request, Business $business): View
    {
        $data = $this->resolveStockListing($request, $business);

        return view('admin.stock.index', $this->workspace($request, $business) + $data + [
            'lowStockProducts' => $this->lowStockProducts($business, self::LOW_STOCK_THRESHOLD),
            'lowStockThreshold' => self::LOW_STOCK_THRESHOLD,
            'emptyDuplicateCount' => $this->emptyDuplicateBatchCount($business),
        ]);
    }

    public function print(Request $request, Business $business): View
    {
        $data = $this->resolveStockListing($request, $business);

        $locationLabel = 'All branches';
        if (($data['locationUuid'] ?? '') !== '') {
            $locationLabel = $data['locations']->firstWhere('uuid', $data['locationUuid'])?->name ?? 'All branches';
        }

        $categoryLabel = null;
        if (! empty($data['categoryId'])) {
            $categoryLabel = $data['categories']->firstWhere('id', $data['categoryId'])?->name;
        }

        $stockLabel = match ($data['stockFilter']) {
            'on_hand' => 'On hand only',
            'low' => 'Low stock (under '.self::LOW_STOCK_THRESHOLD.')',
            'out' => 'Out of stock / empty batches',
            'all' => 'All batches (incl. empty)',
            default => 'On hand only',
        };

        return view('admin.stock.print', [
            'business' => $business,
            'batches' => $data['batches'],
            'printedAt' => now()->timezone(config('app.timezone')),
            'locationLabel' => $locationLabel,
            'categoryLabel' => $categoryLabel,
            'stockLabel' => $stockLabel,
            'search' => $data['search'],
            'lowStockThreshold' => self::LOW_STOCK_THRESHOLD,
            'autoPrint' => $request->boolean('print', true),
            'backUrl' => route('admin.b.stock.index', array_merge(['business' => $business], $data['filterQuery'])),
        ]);
    }

    public function updateQuantity(Request $request, Business $business, string $batch): RedirectResponse
    {
        $batchModel = ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('uuid', $batch)
            ->firstOrFail();

        $data = $request->validate([
            'qty' => ['required', 'numeric', 'min:0'],
        ]);

        $batchModel->qty = $data['qty'];
        $batchModel->version = $batchModel->version + 1;
        $batchModel->save();

        $query = array_filter([
            'q' => $request->input('q'),
            'location_uuid' => $request->input('location_uuid'),
            'category_id' => $request->input('category_id'),
            'stock' => $request->input('stock'),
        ], static fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('admin.b.stock.index', array_merge(['business' => $business], $query))
            ->with('status', 'Stock quantity updated.');
    }

    /**
     * Keep one empty batch per product × location; delete the rest.
     * Sale/purchase lines reference batches with nullOnDelete, so this is safe.
     */
    public function cleanupEmptyDuplicates(Request $request, Business $business): RedirectResponse
    {
        $keptIds = $this->keptEmptyBatchIdsQuery($business)->pluck('id');

        $deleted = ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('qty', '<=', 0)
            ->when($keptIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $keptIds))
            ->delete();

        return redirect()
            ->route('admin.b.stock.index', array_merge(
                ['business' => $business],
                array_filter([
                    'q' => $request->input('q'),
                    'location_uuid' => $request->input('location_uuid'),
                    'category_id' => $request->input('category_id'),
                    'stock' => $request->input('stock', 'out'),
                ], static fn ($v) => $v !== null && $v !== '')
            ))
            ->with('status', $deleted > 0
                ? "Removed {$deleted} duplicate empty batch".($deleted === 1 ? '' : 'es').'. One empty row kept per product and branch.'
                : 'No duplicate empty batches to remove.');
    }

    /**
     * @return array{
     *     batches: Collection,
     *     locations: Collection,
     *     categories: Collection,
     *     search: string,
     *     locationUuid: string,
     *     categoryId: ?int,
     *     stockFilter: string,
     *     filterQuery: array<string, mixed>
     * }
     */
    private function resolveStockListing(Request $request, Business $business): array
    {
        $search = trim((string) $request->query('q', ''));
        $locationUuid = trim((string) $request->query('location_uuid', ''));
        // Default: hide empty batches so sold-out leftovers don't look like product duplicates.
        $stockFilter = strtolower(trim((string) $request->query('stock', 'on_hand')));
        if (! in_array($stockFilter, ['on_hand', 'all', 'low', 'out'], true)) {
            $stockFilter = 'on_hand';
        }

        $categoryId = $request->integer('category_id') ?: null;
        $categories = Category::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['id', 'name']);
        if ($categoryId !== null && ! $categories->contains('id', $categoryId)) {
            $categoryId = null;
        }

        $locations = $business->locations()->orderByDesc('is_default')->orderBy('name')->get(['id', 'uuid', 'name']);
        $locationId = null;
        if ($locationUuid !== '') {
            $locationId = $locations->firstWhere('uuid', $locationUuid)?->id;
            if ($locationId === null) {
                $locationUuid = '';
            }
        }

        $query = ProductBatch::query()
            ->where('product_batches.business_id', $business->id)
            ->with([
                'product:id,name,sku,category_id,unit',
                'product.category:id,name',
                'location:id,name,uuid',
            ])
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->select('product_batches.*')
            ->orderBy('products.name')
            ->orderBy('product_batches.id');

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('products.name', 'like', $like)
                    ->orWhere('products.sku', 'like', $like)
                    ->orWhere('products.barcode', 'like', $like);
            });
        }

        if ($locationId !== null) {
            $query->where('product_batches.location_id', $locationId);
        }

        if ($categoryId !== null) {
            $query->where('products.category_id', $categoryId);
        }

        if ($stockFilter === 'on_hand') {
            $query->where('product_batches.qty', '>', 0);
        } elseif ($stockFilter === 'low') {
            $query->where('product_batches.qty', '>', 0)
                ->where('product_batches.qty', '<', self::LOW_STOCK_THRESHOLD);
        } elseif ($stockFilter === 'out') {
            // One empty row per product × location (hide leftover sold-out duplicates).
            $query->where('product_batches.qty', '<=', 0)
                ->whereIn('product_batches.id', $this->keptEmptyBatchIdsQuery($business));
        } else {
            // All: every on-hand batch, plus at most one empty row per product × location.
            $query->where(function ($q) use ($business): void {
                $q->where('product_batches.qty', '>', 0)
                    ->orWhereIn('product_batches.id', $this->keptEmptyBatchIdsQuery($business));
            });
        }

        return [
            'batches' => $query->limit(800)->get(),
            'locations' => $locations,
            'categories' => $categories,
            'search' => $search,
            'locationUuid' => $locationUuid,
            'categoryId' => $categoryId,
            'stockFilter' => $stockFilter,
            'filterQuery' => array_filter([
                'q' => $search !== '' ? $search : null,
                'location_uuid' => $locationUuid !== '' ? $locationUuid : null,
                'category_id' => $categoryId,
                'stock' => $stockFilter !== 'on_hand' ? $stockFilter : null,
            ], static fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * Products whose on-hand total (all batches) is below the threshold.
     *
     * @return Collection<int, object{name: string, sku: ?string, total_qty: float}>
     */
    private function lowStockProducts(Business $business, float $threshold): Collection
    {
        return Product::query()
            ->where('products.business_id', $business->id)
            ->where('products.track_stock', true)
            ->leftJoin('product_batches', 'product_batches.product_id', '=', 'products.id')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->selectRaw('products.name, products.sku, COALESCE(SUM(product_batches.qty), 0) as total_qty')
            ->havingRaw('COALESCE(SUM(product_batches.qty), 0) < ?', [$threshold])
            ->orderBy('total_qty')
            ->orderBy('products.name')
            ->limit(50)
            ->get();
    }

    /**
     * Newest empty batch id per product × location (for display / cleanup keep-list).
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function keptEmptyBatchIdsQuery(Business $business)
    {
        return DB::table('product_batches')
            ->selectRaw('MAX(id) as id')
            ->where('business_id', $business->id)
            ->where('qty', '<=', 0)
            ->groupBy('product_id', 'location_id');
    }

    private function emptyDuplicateBatchCount(Business $business): int
    {
        $emptyTotal = ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('qty', '<=', 0)
            ->count();

        $kept = (int) $this->keptEmptyBatchIdsQuery($business)->get()->count();

        return max(0, $emptyTotal - $kept);
    }
}
