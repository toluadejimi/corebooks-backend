<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    use ResolvesWorkspace;

    public function overview(Request $request, Business $business): View
    {
        $productCount = Product::query()->where('business_id', $business->id)->count();
        $batchRows = ProductBatch::query()
            ->where('business_id', $business->id)
            ->with(['product:id,name,cost_price', 'location:id,name'])
            ->get();
        $totalQty = (float) $batchRows->sum('qty');
        $stockValue = $batchRows->sum(function (ProductBatch $b) {
            $cost = (float) ($b->product?->cost_price ?? 0);

            return (float) $b->qty * $cost;
        });
        $lowStock = Product::query()
            ->where('business_id', $business->id)
            ->withSum('batches', 'qty')
            ->get()
            ->filter(fn (Product $p) => (float) ($p->batches_sum_qty ?? 0) <= (int) $p->low_stock_threshold)
            ->count();

        return view('admin.workspace.overview', $this->workspace($request, $business) + [
            'productCount' => $productCount,
            'totalQty' => $totalQty,
            'stockValue' => $stockValue,
            'lowStockCount' => $lowStock,
            'locationCount' => $business->locations()->count(),
            'teamCount' => $business->users()->count(),
        ]);
    }
}
