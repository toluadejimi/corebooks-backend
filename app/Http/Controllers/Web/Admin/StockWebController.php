<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\ProductBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockWebController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request, Business $business): View
    {
        $batches = ProductBatch::query()
            ->where('business_id', $business->id)
            ->with(['product:id,name,sku', 'location:id,name'])
            ->orderByDesc('updated_at')
            ->limit(800)
            ->get();

        return view('admin.stock.index', $this->workspace($request, $business) + [
            'batches' => $batches,
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

        return redirect()
            ->route('admin.b.stock.index', $business)
            ->with('status', 'Stock quantity updated.');
    }
}
