<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Business;
use App\Models\JournalLine;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BankTransactionController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'bank_account_uuid' => ['required', 'uuid'],
            'unreconciled_only' => ['nullable', 'boolean'],
        ]);

        $bank = BankAccount::query()
            ->where('business_id', $business->id)
            ->where('uuid', $data['bank_account_uuid'])
            ->firstOrFail();

        $q = BankTransaction::query()->where('bank_account_id', $bank->id)->orderByDesc('txn_date')->orderByDesc('id');
        if ($request->boolean('unreconciled_only')) {
            $q->whereNull('reconciled_at');
        }
        $rows = $q->with('matchedLine')->limit(200)->get();

        return response()->json([
            'data' => $rows->map(fn (BankTransaction $t) => $this->row($t)),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'bank_account_uuid' => ['required', 'uuid'],
            'txn_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);

        $bank = BankAccount::query()
            ->where('business_id', $business->id)
            ->where('uuid', $data['bank_account_uuid'])
            ->firstOrFail();

        $t = BankTransaction::query()->create([
            'business_id' => $business->id,
            'bank_account_id' => $bank->id,
            'uuid' => (string) Str::uuid(),
            'txn_date' => $data['txn_date'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'reference' => $data['reference'] ?? null,
        ]);

        return response()->json(['data' => $this->row($t->load('matchedLine'))], 201);
    }

    public function update(Request $request, Business $business, BankTransaction $bankTransaction): JsonResponse
    {
        abort_unless((int) $bankTransaction->business_id === (int) $business->id, 404);

        $data = $request->validate([
            'reconciled' => ['sometimes', 'boolean'],
            'matched_journal_line_uuid' => ['nullable', 'uuid'],
        ]);

        if (array_key_exists('reconciled', $data)) {
            if ($data['reconciled']) {
                $bankTransaction->reconciled_at = now();
                $bankTransaction->reconciled_by_user_id = $request->user()->id;
            } else {
                $bankTransaction->reconciled_at = null;
                $bankTransaction->reconciled_by_user_id = null;
                $bankTransaction->matched_journal_line_id = null;
            }
        }

        if (array_key_exists('matched_journal_line_uuid', $data)) {
            if ($data['matched_journal_line_uuid'] === null || $data['matched_journal_line_uuid'] === '') {
                $bankTransaction->matched_journal_line_id = null;
            } else {
                $line = JournalLine::query()
                    ->where('uuid', $data['matched_journal_line_uuid'])
                    ->whereHas('entry', fn ($q) => $q->where('business_id', $business->id))
                    ->firstOrFail();
                $bankTransaction->matched_journal_line_id = $line->id;
            }
        }

        $bankTransaction->save();

        return response()->json(['data' => $this->row($bankTransaction->fresh(['matchedLine']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(BankTransaction $t): array
    {
        return [
            'uuid' => $t->uuid,
            'txn_date' => $t->txn_date?->toDateString(),
            'amount' => (float) $t->amount,
            'description' => $t->description,
            'reference' => $t->reference,
            'reconciled_at' => $t->reconciled_at?->toIso8601String(),
            'matched_journal_line_uuid' => $t->matchedLine?->uuid,
        ];
    }
}
