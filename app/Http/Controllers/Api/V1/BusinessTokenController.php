<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Services\BusinessTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BusinessTokenController extends Controller
{
    public function __construct(
        private readonly BusinessTokenService $tokens,
    ) {}

    public function consume(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:app_search'],
        ]);

        $cost = match ($data['reason']) {
            'app_search' => PlatformSetting::getInt('token_app_search_cost', 1),
            default => 0,
        };

        if ($cost <= 0) {
            return response()->json(['ok' => true, 'token_balance' => (int) $business->token_balance, 'charged' => 0]);
        }

        $business->refresh();
        if ((int) $business->token_balance < $cost) {
            return response()->json([
                'message' => 'Insufficient token balance.',
                'balance' => (int) $business->token_balance,
                'required' => $cost,
            ], 402);
        }

        try {
            $this->tokens->debit($business, $request->user(), $data['reason'], $cost, null);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'INSUFFICIENT_TOKENS') {
                $business->refresh();

                return response()->json([
                    'message' => 'Insufficient token balance.',
                    'balance' => (int) $business->token_balance,
                    'required' => $cost,
                ], 402);
            }

            throw $e;
        }

        $business->refresh();

        return response()->json([
            'ok' => true,
            'charged' => $cost,
            'token_balance' => (int) $business->token_balance,
        ]);
    }
}
