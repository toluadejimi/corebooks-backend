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
use App\Services\AccountFundsService;
use App\Services\PurchaseReceiveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class PurchaseWebController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        protected PurchaseReceiveService $purchaseReceive,
        protected AccountFundsService $funds,
    ) {}

    public function index(Request $request, Business $business): View
    {
        $orders = PurchaseOrder::query()
            ->where('business_id', $business->id)
            ->with(['supplier', 'location'])
            ->withCount('lines')
            ->orderByRaw("CASE WHEN status = 'draft' THEN 0 ELSE 1 END")
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
        return $this->formView($request, $business, null);
    }

    public function edit(Request $request, Business $business, string $purchaseUuid): View|RedirectResponse
    {
        $po = $this->findDraft($business, $purchaseUuid);
        if ($po instanceof RedirectResponse) {
            return $po;
        }

        $po->load(['lines.product', 'supplier', 'location']);

        return $this->formView($request, $business, $po);
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $intent = $request->input('intent') === 'draft' ? 'draft' : 'receive';

        return $this->persist($request, $business, null, $intent);
    }

    public function saveDraft(Request $request, Business $business, string $purchaseUuid): RedirectResponse
    {
        $po = $this->findDraft($business, $purchaseUuid);
        if ($po instanceof RedirectResponse) {
            return $po;
        }

        return $this->persist($request, $business, $po, 'draft');
    }

    public function receiveDraft(Request $request, Business $business, string $purchaseUuid): RedirectResponse
    {
        $po = $this->findDraft($business, $purchaseUuid);
        if ($po instanceof RedirectResponse) {
            return $po;
        }

        return $this->persist($request, $business, $po, 'receive');
    }

    /**
     * Back-compat for draft forms that still POST/PUT to /purchases/{uuid}.
     */
    public function update(Request $request, Business $business, string $purchaseUuid): RedirectResponse
    {
        $intent = $request->input('intent') === 'draft' ? 'draft' : 'receive';

        $po = $this->findDraft($business, $purchaseUuid);
        if ($po instanceof RedirectResponse) {
            return $po;
        }

        return $this->persist($request, $business, $po, $intent);
    }

    public function show(Request $request, Business $business, string $purchaseUuid): View|RedirectResponse
    {
        $purchaseOrder = $this->findPurchase($business, $purchaseUuid);

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

        if ($purchaseOrder->status === 'draft') {
            $ws = $this->workspace($request, $business);
            if ($ws['canManage'] ?? false) {
                return redirect()->route('admin.b.purchases.edit', [$business, $purchaseOrder->uuid]);
            }
        }

        $purchaseOrder->load([
            'lines.product',
            'lines.productBatch',
            'supplier',
            'location',
            'payments.glAccount',
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

    private function formView(Request $request, Business $business, ?PurchaseOrder $purchase): View
    {
        $products = Product::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['uuid', 'name', 'sku', 'barcode', 'cost_price']);

        $suppliers = Supplier::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get();

        $locations = $business->locations()->orderByDesc('is_default')->orderBy('name')->get();

        $draftLines = [];
        if ($purchase !== null) {
            foreach ($purchase->lines as $line) {
                $draftLines[] = [
                    'product_uuid' => $line->product?->uuid,
                    'qty' => (string) $line->qty,
                    'unit_cost' => (string) $line->unit_cost,
                    'expiry_date' => $line->expiry_date?->format('Y-m-d'),
                ];
            }
        }

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.purchases.create', $this->workspace($request, $business) + [
            'products' => $products,
            'suppliers' => $suppliers,
            'locations' => $locations,
            'accounts' => $this->funds->listAccounts($business),
            'today' => now()->toDateString(),
            'purchase' => $purchase,
            'draftLines' => $draftLines,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    private function persist(Request $request, Business $business, ?PurchaseOrder $existing, string $intent): RedirectResponse
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

        $rules = [
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
        ];

        if ($intent === 'receive') {
            $rules['payments'] = ['required', 'array', 'min:1'];
            $rules['payments.*.method'] = ['required', 'string', Rule::in(['cash', 'transfer', 'pos'])];
            $rules['payments.*.amount'] = ['required', 'numeric', 'min:0.01'];
            $rules['payments.*.account_uuid'] = ['required', 'string', 'max:128'];
        }

        $validated = $request->validate($rules);

        if (empty($validated['supplier_uuid'])) {
            $name = trim((string) ($validated['supplier_name'] ?? ''));
            if ($name === '') {
                return redirect()->back()->withErrors(['supplier_name' => 'Choose a supplier or enter a new supplier name.'])->withInput();
            }
        }

        try {
            if ($intent === 'draft') {
                $po = $this->purchaseReceive->saveDraft(
                    $business,
                    $validated['location_uuid'],
                    $validated['lines'],
                    $validated['supplier_uuid'] ?? null,
                    $validated['supplier_name'] ?? null,
                    $validated['supplier_phone'] ?? null,
                    $validated['ordered_at'] ?? null,
                    $existing,
                );

                return redirect()
                    ->route('admin.b.purchases.edit', [$business, $po->uuid])
                    ->with('status', 'Draft purchase saved. You can continue editing or receive stock when ready.');
            }

            $po = $this->purchaseReceive->receive(
                $business,
                $validated['location_uuid'],
                $validated['lines'],
                $validated['supplier_uuid'] ?? null,
                $validated['supplier_name'] ?? null,
                $validated['supplier_phone'] ?? null,
                $validated['ordered_at'] ?? null,
                $validated['payments'],
                $existing,
            );
        } catch (InvalidArgumentException $e) {
            if ($existing !== null) {
                $fresh = $existing->fresh();
                if ($fresh !== null && $fresh->status === 'received') {
                    return redirect()
                        ->route('admin.b.purchases.show', [$business, $fresh->uuid])
                        ->with('status', 'This purchase was already received into stock.');
                }
            }

            return redirect()->back()->withErrors(['purchase' => $e->getMessage()])->withInput();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return redirect()->back()->withErrors(['purchase' => 'Could not save this purchase. Check products and quantities, then try again.'])->withInput();
        }

        return redirect()
            ->route('admin.b.purchases.show', [$business, $po->uuid])
            ->with('status', 'Purchase received and stock updated.');
    }

    private function findDraft(Business $business, string $purchaseUuid): PurchaseOrder|RedirectResponse
    {
        $po = $this->findPurchase($business, $purchaseUuid);
        if ($po === null) {
            abort(404);
        }

        if ($po->status === 'received') {
            return redirect()
                ->route('admin.b.purchases.show', [$business, $po->uuid])
                ->with('status', 'This purchase was already received into stock.');
        }

        if ($po->status !== 'draft') {
            return redirect()
                ->route('admin.b.purchases.show', [$business, $po->uuid])
                ->withErrors(['purchase' => 'Only draft purchases can be edited.']);
        }

        return $po;
    }

    private function findPurchase(Business $business, string $purchaseUuid): ?PurchaseOrder
    {
        $purchaseUuid = trim($purchaseUuid);
        if ($purchaseUuid === '') {
            return null;
        }

        return PurchaseOrder::query()
            ->where('business_id', $business->id)
            ->where('uuid', $purchaseUuid)
            ->first();
    }
}
