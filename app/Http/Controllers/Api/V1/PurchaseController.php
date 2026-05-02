<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\PurchaseReceiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseReceiveService $purchases,
    ) {}

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'location_uuid' => ['required', 'uuid'],
            'supplier_uuid' => ['nullable', 'uuid'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'supplier_phone' => ['nullable', 'string', 'max:32'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_uuid' => ['required', 'uuid'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.expiry_date' => ['nullable', 'date'],
        ]);

        $po = $this->purchases->receive(
            $business,
            $data['location_uuid'],
            $data['lines'],
            $data['supplier_uuid'] ?? null,
            $data['supplier_name'] ?? null,
            $data['supplier_phone'] ?? null,
        );

        return response()->json([
            'data' => [
                'uuid' => $po->uuid,
                'total' => (float) $po->total,
                'location_uuid' => $po->location?->uuid,
                'supplier_uuid' => $po->supplier?->uuid,
                'lines' => $po->lines->map(fn ($l) => [
                    'product_uuid' => $l->product?->uuid,
                    'qty' => (float) $l->qty,
                    'unit_cost' => (float) $l->unit_cost,
                ]),
            ],
        ], 201);
    }
}
