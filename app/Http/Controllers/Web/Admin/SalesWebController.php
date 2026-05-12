<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Sale;
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

    public function show(Request $request, Business $business, Sale $sale): View
    {
        abort_if($sale->business_id !== $business->id, 404);

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
