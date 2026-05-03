<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\GlAccount;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function index(Business $business): JsonResponse
    {
        $this->ledger->ensureDefaultChart($business);
        $rows = BankAccount::query()->where('business_id', $business->id)->with('glAccount')->orderBy('name')->get();

        return response()->json([
            'data' => $rows->map(fn (BankAccount $b) => [
                'uuid' => $b->uuid,
                'name' => $b->name,
                'currency' => $b->currency,
                'gl_account_uuid' => $b->glAccount?->uuid,
                'is_active' => (bool) $b->is_active,
            ]),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'currency' => ['nullable', 'string', 'max:8'],
            'gl_account_uuid' => ['required', 'uuid'],
        ]);

        $this->ledger->ensureDefaultChart($business);
        $gl = GlAccount::query()
            ->where('business_id', $business->id)
            ->where('uuid', $data['gl_account_uuid'])
            ->firstOrFail();
        abort_unless(in_array($gl->type, ['asset'], true), 422, 'Bank link must be an asset account (e.g. Bank deposits).');

        $b = BankAccount::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'currency' => $data['currency'] ?? $business->currency ?? 'NGN',
            'gl_account_id' => $gl->id,
            'is_active' => true,
        ]);

        return response()->json([
            'data' => [
                'uuid' => $b->uuid,
                'name' => $b->name,
                'currency' => $b->currency,
                'gl_account_uuid' => $gl->uuid,
            ],
        ], 201);
    }
}
