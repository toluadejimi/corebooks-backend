<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\GlAccount;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

    /**
     * Owner-only: add a non-system account to the chart (e.g. sub-accounts, extra revenue lines).
     */
    public function store(Request $request, Business $business): JsonResponse
    {
        $this->ledger->ensureDefaultChart($business);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['asset', 'liability', 'equity', 'revenue', 'expense'])],
            'parent_uuid' => ['nullable', 'uuid'],
        ]);

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = $this->ledger->allocateNextAccountCode($business, $data['type']);
        } else {
            $exists = GlAccount::query()
                ->where('business_id', $business->id)
                ->where('code', $code)
                ->exists();
            if ($exists) {
                return response()->json(['message' => 'That account code is already in use.'], 422);
            }
        }

        $parentId = null;
        if (! empty($data['parent_uuid'])) {
            $parent = GlAccount::query()
                ->where('business_id', $business->id)
                ->where('uuid', $data['parent_uuid'])
                ->firstOrFail();
            $parentId = $parent->id;
        }

        $nextSort = (int) (GlAccount::query()->where('business_id', $business->id)->max('sort_order') ?? 0) + 1;

        $account = GlAccount::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'code' => $code,
            'name' => trim($data['name']),
            'type' => $data['type'],
            'parent_id' => $parentId,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $nextSort,
        ]);

        return response()->json([
            'data' => [
                'uuid' => $account->uuid,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'is_system' => false,
                'is_active' => true,
            ],
        ], 201);
    }
}
