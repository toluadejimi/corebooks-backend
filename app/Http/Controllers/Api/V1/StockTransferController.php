<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly StockTransferService $transfers,
    ) {}

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'from_location_uuid' => ['required', 'uuid'],
            'to_location_uuid' => ['required', 'uuid'],
            'product_uuid' => ['required', 'uuid'],
            'qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        $result = $this->transfers->transfer(
            $business,
            (int) $request->user()->id,
            $data['from_location_uuid'],
            $data['to_location_uuid'],
            $data['product_uuid'],
            (float) $data['qty'],
        );

        return response()->json(['data' => $result], 201);
    }
}
