<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Expense;
use App\Models\GlAccount;
use App\Services\AccountFundsService;
use App\Services\FundAccountService;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseApiController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
        private readonly AccountFundsService $funds,
        private readonly FundAccountService $fundAccounts,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $q = Expense::query()
            ->where('business_id', $business->id)
            ->with(['location', 'glAccount']);

        if ($loc = $request->query('location_uuid')) {
            $id = $business->locations()->where('uuid', $loc)->value('id');
            if ($id) {
                $q->where('location_id', $id);
            }
        }

        $rows = $q->orderByDesc('paid_at')->orderByDesc('id')->limit(200)->get();

        return response()->json([
            'data' => $rows->map(fn (Expense $e) => $this->row($e)),
            'accounts' => $this->funds->listAccounts($business),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['nullable', 'date'],
            'location_uuid' => ['nullable', 'uuid'],
            'account_uuid' => ['nullable', 'string', 'max:128'],
        ]);

        $locationId = null;
        if (! empty($data['location_uuid'])) {
            $locationId = $business->locations()->where('uuid', $data['location_uuid'])->value('id');
            abort_if($locationId === null, 422, 'Invalid branch.');
        }

        // Resolve the funds account (cash on hand or a bank). If the caller
        // skips it, fall back to Cash on hand so legacy clients keep working.
        $fund = $this->fundAccounts->resolveGlAccount($business, $data['account_uuid'] ?? null);
        $this->fundAccounts->assertHasFunds($business, $fund, (float) $data['amount']);

        $e = Expense::query()->create([
            'business_id' => $business->id,
            'location_id' => $locationId,
            'gl_account_id' => $fund->id,
            'uuid' => (string) Str::uuid(),
            'category' => $data['category'] ?? null,
            'amount' => $data['amount'],
            'notes' => $data['notes'] ?? null,
            'paid_at' => isset($data['paid_at']) ? $data['paid_at'] : now(),
            'version' => 1,
        ]);

        $this->ledger->postExpenseJournal($business, $e);

        return response()->json([
            'data' => $this->row($e->load(['location', 'glAccount'])),
            'accounts' => $this->funds->listAccounts($business),
        ], 201);
    }

    public function update(Request $request, Business $business, string $expenseUuid): JsonResponse
    {
        $expense = Expense::query()
            ->where('business_id', $business->id)
            ->where('uuid', $expenseUuid)
            ->firstOrFail();

        $data = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'category' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'paid_at' => ['nullable', 'date'],
            'location_uuid' => ['nullable', 'uuid'],
            'account_uuid' => ['nullable', 'string', 'max:128'],
        ]);

        if (array_key_exists('location_uuid', $data)) {
            if ($data['location_uuid'] === null || $data['location_uuid'] === '') {
                $expense->location_id = null;
            } else {
                $locationId = $business->locations()->where('uuid', $data['location_uuid'])->value('id');
                abort_if($locationId === null, 422, 'Invalid branch.');
                $expense->location_id = $locationId;
            }
        }

        if (isset($data['amount'])) {
            $expense->amount = $data['amount'];
        }
        if (array_key_exists('category', $data)) {
            $expense->category = $data['category'];
        }
        if (array_key_exists('notes', $data)) {
            $expense->notes = $data['notes'];
        }
        if (array_key_exists('paid_at', $data)) {
            $expense->paid_at = $data['paid_at'];
        }

        if (array_key_exists('account_uuid', $data)) {
            $fund = $this->fundAccounts->resolveGlAccount($business, $data['account_uuid']);
            $expense->gl_account_id = $fund->id;
        }

        // Available balance check: temporarily reverse the old journal so the
        // *new* amount is compared against the account balance excluding this
        // expense, otherwise editing an existing expense would falsely fail.
        $this->ledger->voidBySource($business, 'expense', $expense->uuid);

        $fundForCheck = $expense->gl_account_id !== null
            ? GlAccount::query()->where('business_id', $business->id)->where('id', $expense->gl_account_id)->first()
            : null;
        if ($fundForCheck === null) {
            $fundForCheck = GlAccount::query()
                ->where('business_id', $business->id)
                ->where('code', GeneralLedgerService::CODE_CASH)
                ->firstOrFail();
            $expense->gl_account_id = $fundForCheck->id;
        }

        try {
            $this->fundAccounts->assertHasFunds($business, $fundForCheck, (float) $expense->amount);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-post the original journal so we don't leave the books in a bad state.
            $this->ledger->postExpenseJournal($business, $expense);
            throw $e;
        }

        $expense->version = (int) $expense->version + 1;
        $expense->save();

        $this->ledger->postExpenseJournal($business, $expense->fresh());

        return response()->json([
            'data' => $this->row($expense->fresh(['location', 'glAccount'])),
            'accounts' => $this->funds->listAccounts($business),
        ]);
    }

    public function destroy(Request $request, Business $business, string $expenseUuid): JsonResponse
    {
        $expense = Expense::query()
            ->where('business_id', $business->id)
            ->where('uuid', $expenseUuid)
            ->firstOrFail();
        $this->ledger->voidBySource($business, 'expense', $expense->uuid);
        $expense->delete();

        return response()->json([
            'ok' => true,
            'accounts' => $this->funds->listAccounts($business),
        ]);
    }

    private function row(Expense $e): array
    {
        return [
            'uuid' => $e->uuid,
            'amount' => (float) $e->amount,
            'category' => $e->category,
            'notes' => $e->notes,
            'paid_at' => $e->paid_at?->toIso8601String(),
            'location_uuid' => $e->location?->uuid,
            'location_name' => $e->location?->name,
            'account_gl_uuid' => $e->glAccount?->uuid,
            'account_name' => $e->glAccount?->name,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
