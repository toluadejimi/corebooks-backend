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
        $sales = Sale::query()
            ->where('business_id', $business->id)
            ->with(['lines', 'payments'])
            ->orderByDesc('sold_at')
            ->paginate($request->integer('per_page', 30));

        return response()->json($sales);
    }

    public function show(Business $business, Sale $sale): JsonResponse
    {
        abort_if($sale->business_id !== $business->id, 404);

        $sale->load(['lines.product', 'payments']);

        return response()->json(['data' => $this->saleResponse($sale)]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'location_uuid' => ['required', 'uuid'],
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
            'subtotal' => (float) $sale->subtotal,
            'tax_total' => (float) $sale->tax_total,
            'discount_total' => (float) $sale->discount_total,
            'grand_total' => (float) $sale->grand_total,
            'sold_at' => $sale->sold_at?->toIso8601String(),
            'lines' => $sale->lines->map(fn ($l) => [
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
