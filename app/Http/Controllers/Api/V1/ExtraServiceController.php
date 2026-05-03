<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExtraService;
use Illuminate\Http\JsonResponse;

class ExtraServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = ExtraService::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $rows->map(fn (ExtraService $s) => $s->toApiArray())->values()->all(),
        ]);
    }
}
