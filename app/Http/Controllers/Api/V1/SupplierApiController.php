<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupplierApiController extends Controller
{
    public function index(Business $business): JsonResponse
    {
        $rows = Supplier::query()
            ->where('business_id', $business->id)
            ->withCount('purchaseOrders')
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'uuid', 'name', 'phone', 'email', 'address', 'balance', 'version']);

        return response()->json([
            'data' => $rows->map(fn (Supplier $s) => $this->present($s)),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $this->validatePayload($request, $business);

        $supplier = Supplier::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => trim($data['name']),
            'phone' => $this->trimOrNull($data['phone'] ?? null),
            'email' => $this->trimOrNull($data['email'] ?? null),
            'address' => $this->trimOrNull($data['address'] ?? null),
            'balance' => 0,
            'version' => 1,
        ]);

        $supplier->loadCount('purchaseOrders');

        return response()->json(['data' => $this->present($supplier)], 201);
    }

    public function update(Request $request, Business $business, string $supplierUuid): JsonResponse
    {
        $supplier = $this->findForBusiness($business, $supplierUuid);
        if ($supplier === null) {
            return response()->json(['message' => 'Supplier not found in this workspace.'], 404);
        }

        $data = $this->validatePayload($request, $business, $supplier);

        $supplier->fill([
            'name' => trim($data['name']),
            'phone' => $this->trimOrNull($data['phone'] ?? null),
            'email' => $this->trimOrNull($data['email'] ?? null),
            'address' => $this->trimOrNull($data['address'] ?? null),
        ]);
        $supplier->version = (int) $supplier->version + 1;
        $supplier->save();

        $supplier->loadCount('purchaseOrders');

        return response()->json(['data' => $this->present($supplier)]);
    }

    public function destroy(Business $business, string $supplierUuid): JsonResponse
    {
        $supplier = $this->findForBusiness($business, $supplierUuid);
        if ($supplier === null) {
            return response()->json(['message' => 'Supplier not found in this workspace.'], 404);
        }

        if ($supplier->purchaseOrders()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this supplier while purchase history exists. Reassign or remove the purchases first.',
            ], 422);
        }

        $supplier->delete();

        return response()->json(['data' => ['uuid' => $supplier->uuid, 'deleted' => true]]);
    }

    private function findForBusiness(Business $business, string $supplierUuid): ?Supplier
    {
        try {
            return Supplier::query()
                ->where('business_id', $business->id)
                ->where('uuid', $supplierUuid)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * @return array{name:string, phone?:?string, email?:?string, address?:?string}
     */
    private function validatePayload(Request $request, Business $business, ?Supplier $existing = null): array
    {
        $uniqueName = Rule::unique('suppliers', 'name')->where('business_id', $business->id);
        if ($existing !== null) {
            $uniqueName = $uniqueName->ignore($existing->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255', $uniqueName],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trim = trim($value);

        return $trim === '' ? null : $trim;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Supplier $s): array
    {
        return [
            'uuid' => $s->uuid,
            'name' => $s->name,
            'phone' => $s->phone,
            'email' => $s->email,
            'address' => $s->address,
            'balance' => (float) $s->balance,
            'purchase_orders_count' => (int) ($s->purchase_orders_count ?? 0),
            'can_delete' => (int) ($s->purchase_orders_count ?? 0) === 0,
            'version' => (int) $s->version,
        ];
    }
}
