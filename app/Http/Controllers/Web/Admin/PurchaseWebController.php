<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Services\PurchaseReceiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class PurchaseWebController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        protected PurchaseReceiveService $purchaseReceive,
    ) {}

    public function index(Request $request, Business $business): View
    {
        $orders = PurchaseOrder::query()
            ->where('business_id', $business->id)
            ->with(['supplier', 'location'])
            ->withCount('lines')
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.purchases.index', $this->workspace($request, $business) + [
            'orders' => $orders,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    public function create(Request $request, Business $business): View
    {
        $products = Product::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['uuid', 'name', 'cost_price']);

        $suppliers = Supplier::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get();

        $locations = $business->locations()->orderByDesc('is_default')->orderBy('name')->get();

        return view('admin.purchases.create', $this->workspace($request, $business) + [
            'products' => $products,
            'suppliers' => $suppliers,
            'locations' => $locations,
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $linesInput = $request->input('lines', []);
        $linesFiltered = [];
        if (is_array($linesInput)) {
            foreach ($linesInput as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $pu = trim((string) ($line['product_uuid'] ?? ''));
                if ($pu === '') {
                    continue;
                }
                $linesFiltered[] = [
                    'product_uuid' => $pu,
                    'qty' => $line['qty'] ?? null,
                    'unit_cost' => $line['unit_cost'] ?? null,
                    'expiry_date' => $line['expiry_date'] ?? null,
                ];
            }
        }

        $request->merge(['lines' => $linesFiltered]);

        $validated = $request->validate([
            'location_uuid' => ['required', 'uuid', Rule::exists('locations', 'uuid')->where('business_id', $business->id)],
            'supplier_uuid' => ['nullable', 'uuid', Rule::exists('suppliers', 'uuid')->where('business_id', $business->id)],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_phone' => ['nullable', 'string', 'max:32'],
            'ordered_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_uuid' => ['required', 'uuid', Rule::exists('products', 'uuid')->where('business_id', $business->id)],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ]);

        if (empty($validated['supplier_uuid'])) {
            $name = trim((string) ($validated['supplier_name'] ?? ''));
            if ($name === '') {
                return redirect()->back()->withErrors(['supplier_name' => 'Choose a supplier or enter a new supplier name.'])->withInput();
            }
        }

        try {
            $po = $this->purchaseReceive->receive(
                $business,
                $validated['location_uuid'],
                $validated['lines'],
                $validated['supplier_uuid'] ?? null,
                $validated['supplier_name'] ?? null,
                $validated['supplier_phone'] ?? null,
                $validated['ordered_at'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['purchase' => $e->getMessage()])->withInput();
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withErrors(['purchase' => 'Could not save this purchase. Check products and quantities, then try again.'])->withInput();
        }

        return redirect()
            ->route('admin.b.purchases.show', [$business, $po->uuid])
            ->with('status', 'Purchase received and stock updated.');
    }

    public function show(Request $request, Business $business, string $purchaseUuid): View|RedirectResponse
    {
        $purchaseOrder = PurchaseOrder::query()
            ->where('business_id', $business->id)
            ->where('uuid', $purchaseUuid)
            ->first();

        if ($purchaseOrder === null) {
            $batch = ProductBatch::query()
                ->where('business_id', $business->id)
                ->where('uuid', $purchaseUuid)
                ->first();
            if ($batch !== null) {
                $line = PurchaseOrderLine::query()
                    ->where('product_batch_id', $batch->id)
                    ->whereHas('purchaseOrder', static fn ($q) => $q->where('business_id', $business->id))
                    ->with('purchaseOrder')
                    ->first();
                if ($line?->purchaseOrder !== null) {
                    return redirect()->route('admin.b.purchases.show', [$business, $line->purchaseOrder->uuid]);
                }
            }

            abort(404);
        }

        $purchaseOrder->load([
            'lines.product',
            'lines.productBatch',
            'supplier',
            'location',
        ]);

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.purchases.show', $this->workspace($request, $business) + [
            'po' => $purchaseOrder,
            'currencySymbol' => $currencySymbol,
        ]);
    }
}
