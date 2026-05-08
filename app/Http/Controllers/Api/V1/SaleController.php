<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Sale;
use App\Services\SaleCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleCheckoutService $checkout,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $query = Sale::query()
            ->where('business_id', $business->id)
            ->with(['lines', 'payments', 'customer:id,uuid,name,is_walk_in']);

        $customerUuid = trim((string) $request->query('customer_uuid', ''));
        if ($customerUuid !== '') {
            $query->whereHas('customer', fn ($q) => $q->where('uuid', $customerUuid));
        }

        $sales = $query
            ->orderByDesc('sold_at')
            ->paginate($request->integer('per_page', 30));

        return response()->json($sales);
    }

    public function show(Business $business, Sale $sale): JsonResponse
    {
        abort_if($sale->business_id !== $business->id, 404);

        $sale->load(['lines.product', 'payments', 'customer']);

        return response()->json(['data' => $this->saleResponse($sale)]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'location_uuid' => ['required', 'uuid'],
            'customer_uuid' => ['nullable', 'uuid'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_uuid' => ['required', 'uuid'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.batch_uuid' => ['nullable', 'uuid'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'in:cash,transfer,pos'],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.meta' => ['nullable', 'array'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
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
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->saleResponse($sale)], 201);
    }

    private function saleResponse(Sale $sale): array
    {
        return [
            'uuid' => $sale->uuid,
            'receipt_no' => $sale->receipt_no,
            'status' => $sale->status,
            'subtotal' => (float) $sale->subtotal,
            'tax_total' => (float) $sale->tax_total,
            'discount_total' => (float) $sale->discount_total,
            'grand_total' => (float) $sale->grand_total,
            'sold_at' => $sale->sold_at?->toIso8601String(),
            'customer' => $sale->customer ? [
                'uuid' => $sale->customer->uuid,
                'name' => $sale->customer->name,
                'is_walk_in' => (bool) $sale->customer->is_walk_in,
            ] : null,
            'lines' => $sale->lines->map(fn ($l) => [
                'sale_line_id' => $l->id,
                'product_uuid' => $l->product->uuid,
                'qty' => (float) $l->qty,
                'unit_price' => (float) $l->unit_price,
                'tax_rate' => (float) $l->tax_rate,
                'line_total' => (float) $l->line_total,
            ]),
            'payments' => $sale->payments->map(fn ($p) => [
                'uuid' => $p->uuid,
                'method' => $p->method,
                'amount' => (float) $p->amount,
            ]),
        ];
    }
}
