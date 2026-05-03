<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\GlAccount;
use App\Models\JournalEntry;
use App\Services\GeneralLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $this->ledger->ensureDefaultChart($business);
        $q = $business->journalEntries()->with(['lines.account'])->orderByDesc('entry_date')->orderByDesc('id');
        $paginator = $q->paginate($request->integer('per_page', 30));

        return response()->json($paginator);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.gl_account_uuid' => ['required', 'uuid'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = [];
        foreach ($data['lines'] as $l) {
            $acc = GlAccount::query()
                ->where('business_id', $business->id)
                ->where('uuid', $l['gl_account_uuid'])
                ->firstOrFail();
            $lines[] = [
                'gl_account_id' => $acc->id,
                'debit' => (float) $l['debit'],
                'credit' => (float) $l['credit'],
                'description' => $l['description'] ?? null,
            ];
        }

        try {
            $entry = $this->ledger->createManualEntry(
                $business,
                $data['entry_date'],
                $data['memo'] ?? null,
                $lines,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->entryPayload($entry)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function entryPayload(JournalEntry $entry): array
    {
        $entry->load(['lines.account']);

        return [
            'uuid' => $entry->uuid,
            'entry_date' => $entry->entry_date?->toDateString(),
            'posted_at' => $entry->posted_at?->toIso8601String(),
            'memo' => $entry->memo,
            'source_type' => $entry->source_type,
            'source_uuid' => $entry->source_uuid,
            'lines' => $entry->lines->map(fn ($l) => [
                'gl_account_uuid' => $l->account?->uuid,
                'code' => $l->account?->code,
                'name' => $l->account?->name,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
                'description' => $l->description,
            ]),
        ];
    }
}
