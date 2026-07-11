<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferService $transfers,
    ) {}

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'from_location_uuid' => ['required', 'uuid'],
            'to_location_uuid' => ['required', 'uuid', 'different:from_location_uuid'],
            // Legacy single-product body
            'product_uuid' => ['required_without:lines', 'uuid'],
            'qty' => ['required_without:lines', 'numeric', 'min:0.001'],
            // Multi-product body
            'lines' => ['required_without:product_uuid', 'array', 'min:1'],
            'lines.*.product_uuid' => ['required', 'uuid'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        try {
            if (! empty($data['lines'])) {
                $result = $this->transfers->transferMany(
                    $business,
                    (int) $request->user()->id,
                    $data['from_location_uuid'],
                    $data['to_location_uuid'],
                    $data['lines'],
                );
            } else {
                $result = $this->transfers->transfer(
                    $business,
                    (int) $request->user()->id,
                    $data['from_location_uuid'],
                    $data['to_location_uuid'],
                    $data['product_uuid'],
                    (float) $data['qty'],
                );
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result], 201);
    }
}
