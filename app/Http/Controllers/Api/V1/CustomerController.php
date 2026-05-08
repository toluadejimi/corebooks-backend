<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $this->ensureWalkIn($business);

        $query = Customer::query()
            ->where('business_id', $business->id)
            ->withCount('sales')
            ->withSum(['sales as sales_total' => fn ($q) => $q->where('status', '!=', 'voided')], 'grand_total')
            ->orderByDesc('is_walk_in')
            ->orderBy('name');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $perPage = min(max($request->integer('per_page', 50), 1), 200);
        $page = $query->paginate($perPage);
        $page->setCollection($page->getCollection()->map(fn (Customer $c) => $this->summary($c)));

        return response()->json($page);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = Customer::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => trim($data['name']),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_walk_in' => false,
            'version' => 1,
        ]);

        return response()->json(['data' => $this->summary($customer->fresh())], 201);
    }

    public function show(Request $request, Business $business, string $customer): JsonResponse
    {
        $model = $this->findForBusiness($business, $customer);
        if ($model === null) {
            return response()->json(['message' => 'Customer not found in this workspace.'], 404);
        }

        $sales = Sale::query()
            ->where('business_id', $business->id)
            ->where('customer_id', $model->id)
            ->with(['lines.product:id,uuid,name'])
            ->orderByDesc('sold_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $this->summary($model->loadCount('sales')),
            'recent_sales' => $sales->map(fn (Sale $s) => [
                'uuid' => $s->uuid,
                'receipt_no' => $s->receipt_no,
                'status' => $s->status,
                'grand_total' => (float) $s->grand_total,
                'sold_at' => $s->sold_at?->toIso8601String(),
                'item_count' => $s->lines->count(),
            ]),
        ]);
    }

    public function update(Request $request, Business $business, string $customer): JsonResponse
    {
        $model = $this->findForBusiness($business, $customer);
        if ($model === null) {
            return response()->json(['message' => 'Customer not found in this workspace.'], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $model->fill([
            'name' => trim($data['name']),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'notes' => $data['notes'] ?? null,
            'version' => $model->version + 1,
        ])->save();

        return response()->json(['data' => $this->summary($model->fresh())]);
    }

    public function destroy(Business $business, string $customer): JsonResponse
    {
        $model = $this->findForBusiness($business, $customer);
        if ($model === null) {
            return response()->json(['message' => 'Customer not found in this workspace.'], 404);
        }
        if ($model->is_walk_in) {
            return response()->json(['message' => 'The Walk-in customer cannot be deleted.'], 422);
        }

        $model->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Strictly scope by parent business — never expose a customer from another workspace.
     */
    private function findForBusiness(Business $business, string $customerUuid): ?Customer
    {
        try {
            return $business->customers()
                ->where('uuid', $customerUuid)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    private function ensureWalkIn(Business $business): void
    {
        $exists = Customer::query()
            ->where('business_id', $business->id)
            ->where('is_walk_in', true)
            ->exists();
        if ($exists) {
            return;
        }
        Customer::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Walk-in customer',
            'is_walk_in' => true,
            'version' => 1,
        ]);
    }

    private function summary(Customer $c): array
    {
        return [
            'uuid' => $c->uuid,
            'name' => $c->name,
            'phone' => $c->phone,
            'email' => $c->email,
            'notes' => $c->notes,
            'is_walk_in' => (bool) $c->is_walk_in,
            'sales_count' => (int) ($c->sales_count ?? 0),
            'sales_total' => (float) ($c->sales_total ?? 0),
        ];
    }
}
