<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LoanPartnerBank;
use Illuminate\Http\JsonResponse;

class LoanPartnerBankController extends Controller
{
    public function index(): JsonResponse
    {
        $banks = LoanPartnerBank::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $banks->map(fn (LoanPartnerBank $b) => $b->toApiArray()),
        ]);
    }
}
