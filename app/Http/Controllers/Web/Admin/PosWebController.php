<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Support\ProductUnits;
use App\Services\AccountFundsService;
use App\Services\SaleCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class PosWebController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly SaleCheckoutService $checkout,
        private readonly AccountFundsService $funds,
    ) {}

    public function index(Request $request, Business $business): View
    {
        $locationUuid = $request->query('location_uuid');
        $location = null;
        if (is_string($locationUuid) && $locationUuid !== '') {
            $location = $business->locations()->where('uuid', $locationUuid)->first();
        }
        $location ??= $business->locations()->orderByDesc('is_default')->first()
            ?? $business->locations()->firstOrFail();

        $products = Product::query()
            ->where('business_id', $business->id)
            ->with(['category:id,uuid,name'])
            ->withSum([
                'batches as location_stock_qty' => static fn ($q) => $q->where('location_id', $location->id),
            ], 'qty')
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn (Product $p) => [
                'uuid' => $p->uuid,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'unit' => ProductUnits::normalize($p->unit),
                'image_url' => $p->image_url,
                'selling_price' => (float) $p->selling_price,
                'vat_rate' => $p->vat_rate !== null ? (float) $p->vat_rate : (float) $business->default_vat_rate,
                'track_stock' => (bool) $p->track_stock,
                'stock_qty' => (float) ($p->location_stock_qty ?? 0),
                'category_uuid' => $p->category?->uuid,
                'category_name' => $p->category?->name,
            ])
            ->values();

        $categories = Category::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->get(['uuid', 'name']);

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->orderByRaw('is_walk_in DESC')
            ->orderBy('name')
            ->limit(200)
            ->get(['uuid', 'name', 'is_walk_in', 'credit_enabled']);

        $accounts = collect($this->funds->listAccounts($business))
            ->filter(fn (array $a) => $a['is_active'] ?? true)
            ->values();

        return view('admin.pos.index', $this->workspace($request, $business) + [
            'location' => $location,
            'locations' => $business->locations()->orderByDesc('is_default')->get(),
            'products' => $products,
            'categories' => $categories,
            'customers' => $customers,
            'accounts' => $accounts,
            'defaultVat' => (float) $business->default_vat_rate,
            'currencySymbol' => $this->currencySymbol($business),
            'receiptFooter' => data_get($business->settings, 'receipt_footer') ?: 'Thank you for your patronage.',
        ]);
    }

    public function checkout(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'location_uuid' => ['required', 'uuid'],
            'customer_uuid' => ['nullable', 'uuid'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_uuid' => ['required', 'uuid'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,transfer,pos,credit'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.account_uuid' => ['nullable', 'string', 'max:128'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'sold_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:today'],
        ]);

        try {
            $sale = $this->checkout->checkout(
                $business,
                (int) $request->user()->id,
                $data['location_uuid'],
                $data['lines'],
                $data['payments'],
                $data['idempotency_key'] ?? null,
                (float) ($data['discount_total'] ?? 0),
                $data['customer_uuid'] ?? null,
                $data['sold_at'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $sale->load(['lines.product', 'payments.glAccount', 'customer', 'location']);

        return response()->json([
            'data' => $this->salePayload($business, $sale),
            'receipt_url' => route('admin.b.pos.receipt', [$business, $sale->uuid]),
        ], 201);
    }

    public function quickProduct(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'location_uuid' => ['nullable', 'uuid'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'unit' => ['nullable', 'string', 'max:16'],
        ]);

        $location = $business->locations()->where('is_default', true)->first()
            ?? $business->locations()->firstOrFail();
        if (! empty($data['location_uuid'])) {
            $location = $business->locations()->where('uuid', $data['location_uuid'])->firstOrFail();
        }

        $product = Product::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'unit' => ProductUnits::normalize($data['unit'] ?? null),
            'cost_price' => 0,
            'selling_price' => $data['selling_price'],
            'track_batches' => false,
            'track_stock' => false,
            'vat_rate' => $data['vat_rate'] ?? $business->default_vat_rate,
            'version' => 1,
        ]);

        return response()->json([
            'data' => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'unit' => ProductUnits::normalize($product->unit),
                'selling_price' => (float) $product->selling_price,
                'vat_rate' => (float) $product->vat_rate,
                'track_stock' => false,
                'stock_qty' => 0,
            ],
        ], 201);
    }

    public function receipt(Request $request, Business $business, string $saleUuid): View
    {
        $sale = Sale::query()
            ->where('business_id', $business->id)
            ->where('uuid', $saleUuid)
            ->with(['lines.product', 'payments', 'customer', 'location'])
            ->firstOrFail();

        return view('admin.pos.receipt', [
            'business' => $business,
            'sale' => $sale,
            'currencySymbol' => $this->currencySymbol($business),
            'receiptFooter' => data_get($business->settings, 'receipt_footer') ?: 'Thank you for your patronage.',
            'autoPrint' => $request->boolean('print'),
            'returnToPos' => $request->boolean('return'),
        ]);
    }

    private function salePayload(Business $business, Sale $sale): array
    {
        return [
            'uuid' => $sale->uuid,
            'receipt_no' => $sale->receipt_no,
            'subtotal' => (float) $sale->subtotal,
            'tax_total' => (float) $sale->tax_total,
            'discount_total' => (float) $sale->discount_total,
            'grand_total' => (float) $sale->grand_total,
            'sold_at' => $sale->sold_at?->toIso8601String(),
            'customer' => $sale->customer ? [
                'uuid' => $sale->customer->uuid,
                'name' => $sale->customer->name,
            ] : null,
            'lines' => $sale->lines->map(fn ($l) => [
                'name' => $l->product?->name,
                'qty' => (float) $l->qty,
                'unit' => ProductUnits::normalize($l->product?->unit),
                'line_total' => (float) $l->line_total,
            ]),
            'payments' => $sale->payments->map(fn ($p) => [
                'method' => $p->method,
                'amount' => (float) $p->amount,
            ]),
        ];
    }

    private function currencySymbol(Business $business): string
    {
        return match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };
    }
}
