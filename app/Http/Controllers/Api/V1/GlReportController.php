<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlReportController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function trialBalance(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'as_of' => ['required', 'date'],
        ]);

        $rows = $this->ledger->trialBalance($business, $data['as_of']);
        $totalDr = round(array_sum(array_column($rows, 'debit')), 2);
        $totalCr = round(array_sum(array_column($rows, 'credit')), 2);

        return response()->json([
            'data' => [
                'as_of' => $data['as_of'],
                'accounts' => $rows,
                'total_debit' => $totalDr,
                'total_credit' => $totalCr,
            ],
        ]);
    }
}
