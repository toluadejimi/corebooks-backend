<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExpenseApiController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $q = Expense::query()->where('business_id', $business->id)->with('location');

        if ($loc = $request->query('location_uuid')) {
            $id = $business->locations()->where('uuid', $loc)->value('id');
            if ($id) {
                $q->where('location_id', $id);
            }
        }

        $rows = $q->orderByDesc('paid_at')->orderByDesc('id')->limit(200)->get();

        return response()->json([
            'data' => $rows->map(fn (Expense $e) => $this->row($e)),
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
        ]);

        $locationId = null;
        if (! empty($data['location_uuid'])) {
            $locationId = $business->locations()->where('uuid', $data['location_uuid'])->value('id');
            abort_if($locationId === null, 422, 'Invalid branch.');
        }

        $e = Expense::query()->create([
            'business_id' => $business->id,
            'location_id' => $locationId,
            'uuid' => (string) Str::uuid(),
            'category' => $data['category'] ?? null,
            'amount' => $data['amount'],
            'notes' => $data['notes'] ?? null,
            'paid_at' => isset($data['paid_at']) ? $data['paid_at'] : now(),
            'version' => 1,
        ]);

        return response()->json(['data' => $this->row($e->load('location'))], 201);
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

        $expense->version = (int) $expense->version + 1;
        $expense->save();

        return response()->json(['data' => $this->row($expense->fresh('location'))]);
    }

    public function destroy(Request $request, Business $business, string $expenseUuid): JsonResponse
    {
        $expense = Expense::query()
            ->where('business_id', $business->id)
            ->where('uuid', $expenseUuid)
            ->firstOrFail();
        $expense->delete();

        return response()->json(['ok' => true]);
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
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }
}
