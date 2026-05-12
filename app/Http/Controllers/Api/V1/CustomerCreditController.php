<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerCreditEntry;
use App\Services\GeneralLedgerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Per-customer credit ledger: list charges + payments and record settlements
 * against the running outstanding balance.
 */
class CustomerCreditController extends Controller
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function entries(Request $request, Business $business, string $customer): JsonResponse
    {
        $model = $this->findCustomer($business, $customer);
        if ($model === null) {
            return response()->json(['message' => 'Customer not found in this workspace.'], 404);
        }

        $perPage = min(max($request->integer('per_page', 50), 1), 200);
        $page = CustomerCreditEntry::query()
            ->where('business_id', $business->id)
            ->where('customer_id', $model->id)
            ->with(['sale:id,uuid,receipt_no'])
            ->orderByDesc('occurred_at')
            ->paginate($perPage);

        $page->setCollection($page->getCollection()->map(fn (CustomerCreditEntry $e) => $this->summary($e)));

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'customer' => [
                'uuid' => $model->uuid,
                'name' => $model->name,
                'credit_enabled' => (bool) $model->credit_enabled,
                'credit_limit' => (float) $model->credit_limit,
                'credit_balance' => (float) $model->credit_balance,
                'credit_available' => $this->available($model),
            ],
        ]);
    }

    public function recordPayment(Request $request, Business $business, string $customer): JsonResponse
    {
        $model = $this->findCustomer($business, $customer);
        if ($model === null) {
            return response()->json(['message' => 'Customer not found in this workspace.'], 404);
        }
        if ($model->is_walk_in) {
            return response()->json(['message' => 'The Walk-in customer cannot hold a credit balance.'], 422);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:cash,transfer,pos'],
            'reference' => ['nullable', 'string', 'max:96'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $entry = DB::transaction(function () use ($business, $model, $request, $data): CustomerCreditEntry {
            $locked = Customer::query()->whereKey($model->id)->lockForUpdate()->first();
            $current = (float) $locked->credit_balance;
            $amount = round((float) $data['amount'], 2);

            if ($current <= 0) {
                throw new \InvalidArgumentException('There is no outstanding balance to settle for this customer.');
            }
            // Allow tiny float drift; cap to current balance so we never go negative.
            $applied = min($amount, $current);
            $locked->credit_balance = round($current - $applied, 2);
            $locked->save();

            return CustomerCreditEntry::query()->create([
                'business_id' => $business->id,
                'customer_id' => $locked->id,
                'sale_id' => null,
                'payment_id' => null,
                'user_id' => $request->user()?->id,
                'uuid' => (string) Str::uuid(),
                'type' => 'payment',
                'method' => $data['method'],
                'amount' => $applied,
                'balance_after' => $locked->credit_balance,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'occurred_at' => now(),
            ]);
        });

        $this->ledger->postCustomerCreditPayment($business, $entry);

        return response()->json([
            'data' => $this->summary($entry->fresh(['sale:id,uuid,receipt_no'])),
            'customer' => [
                'uuid' => $model->fresh()->uuid,
                'credit_balance' => (float) $model->fresh()->credit_balance,
                'credit_limit' => (float) $model->credit_limit,
                'credit_available' => $this->available($model->fresh()),
            ],
        ], 201);
    }

    private function findCustomer(Business $business, string $uuid): ?Customer
    {
        try {
            return $business->customers()->where('uuid', $uuid)->firstOrFail();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    private function summary(CustomerCreditEntry $e): array
    {
        return [
            'uuid' => $e->uuid,
            'type' => $e->type,
            'method' => $e->method,
            'amount' => (float) $e->amount,
            'balance_after' => (float) $e->balance_after,
            'reference' => $e->reference,
            'notes' => $e->notes,
            'occurred_at' => $e->occurred_at?->toIso8601String(),
            'sale' => $e->sale ? [
                'uuid' => $e->sale->uuid,
                'receipt_no' => $e->sale->receipt_no,
            ] : null,
        ];
    }

    private function available(Customer $c): float
    {
        $limit = (float) $c->credit_limit;
        $balance = (float) $c->credit_balance;
        if ($limit <= 0) {
            return 0.0;
        }
        return round(max(0, $limit - $balance), 2);
    }
}
