<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;

class GlAccountController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function index(Business $business): JsonResponse
    {
        $this->ledger->ensureDefaultChart($business);
        $rows = $business->glAccounts()->orderBy('code')->get();

        return response()->json([
            'data' => $rows->map(fn ($a) => [
                'uuid' => $a->uuid,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
                'is_system' => (bool) $a->is_system,
                'is_active' => (bool) $a->is_active,
            ]),
        ]);
    }
}
