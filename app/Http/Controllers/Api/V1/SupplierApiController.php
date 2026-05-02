<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class SupplierApiController extends Controller
{
    public function index(Business $business): JsonResponse
    {
        $rows = Supplier::query()
            ->where('business_id', $business->id)
            ->orderBy('name')
            ->limit(500)
            ->get(['uuid', 'name', 'phone']);

        return response()->json([
            'data' => $rows->map(fn (Supplier $s) => [
                'uuid' => $s->uuid,
                'name' => $s->name,
                'phone' => $s->phone,
            ]),
        ]);
    }
}
