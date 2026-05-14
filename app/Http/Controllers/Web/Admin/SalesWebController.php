<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesWebController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request, Business $business): View
    {
        $sales = Sale::query()
            ->where('business_id', $business->id)
            ->with(['location:id,name', 'customer:id,name', 'user:id,name'])
            ->withCount('lines')
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.sales.index', $this->workspace($request, $business) + [
            'sales' => $sales,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    public function show(Request $request, Business $business, string $saleUuid): View|RedirectResponse
    {
        $saleUuid = trim($saleUuid);

        // Try uuid first, then receipt_no — older bookmarks / hand-edited URLs sometimes
        // use the receipt number rather than the uuid. Both lookups are scoped to this
        // workspace so cross-business data never leaks.
        $sale = Sale::query()
            ->where('business_id', $business->id)
            ->where(function ($q) use ($saleUuid): void {
                $q->where('uuid', $saleUuid)
                    ->orWhere('receipt_no', $saleUuid);
            })
            ->first();

        if ($sale === null) {
            return redirect()
                ->route('admin.b.sales.index', $business)
                ->withErrors([
                    'sale' => "We couldn't find that sale (it may have been deleted or belongs to a different workspace).",
                ]);
        }

        // If the URL used the receipt_no, redirect to the canonical uuid URL so the link
        // is share-friendly and consistent with the rest of the admin.
        if ($sale->uuid !== $saleUuid) {
            return redirect()->route('admin.b.sales.show', [$business, $sale->uuid]);
        }

        $sale->load([
            'lines.product',
            'lines.batch',
            'payments',
            'customer',
            'location',
            'user',
        ]);

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.sales.show', $this->workspace($request, $business) + [
            'sale' => $sale,
            'currencySymbol' => $currencySymbol,
        ]);
    }
}
