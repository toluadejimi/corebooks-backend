<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;

class SubscriptionPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'data' => $plans->map(fn (SubscriptionPlan $p) => $p->toApiArray()),
        ]);
    }
}
