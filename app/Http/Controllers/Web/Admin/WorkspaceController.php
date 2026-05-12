<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        protected ReportingService $reporting,
    ) {}

    public function overview(Request $request, Business $business): View
    {
        $productCount = Product::query()->where('business_id', $business->id)->count();
        $totalQty = (float) ProductBatch::query()
            ->where('business_id', $business->id)
            ->sum('qty');
        /** Same basis as Reports → Daily “Stock valuation (cost)”: batch snapshot or catalog cost, capped vs list price. */
        $stockValue = $this->reporting->stockValuation($business, null);
        $lowStock = Product::query()
            ->where('business_id', $business->id)
            ->withSum('batches', 'qty')
            ->get()
            ->filter(fn (Product $p) => (float) ($p->batches_sum_qty ?? 0) <= (int) $p->low_stock_threshold)
            ->count();

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.workspace.overview', $this->workspace($request, $business) + [
            'productCount' => $productCount,
            'totalQty' => $totalQty,
            'stockValue' => $stockValue,
            'currencySymbol' => $currencySymbol,
            'lowStockCount' => $lowStock,
            'locationCount' => $business->locations()->count(),
            'teamCount' => $business->users()->count(),
        ]);
    }
}
