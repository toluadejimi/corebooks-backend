<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Str;

class SupplierWebController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request, Business $business): View
    {
        $suppliers = Supplier::query()
            ->where('business_id', $business->id)
            ->withCount('purchaseOrders')
            ->withSum('purchaseOrders as purchase_orders_total', 'total')
            ->orderBy('name')
            ->get();

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.suppliers.index', $this->workspace($request, $business) + [
            'suppliers' => $suppliers,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    public function create(Request $request, Business $business): View
    {
        return view('admin.suppliers.create', $this->workspace($request, $business));
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        Supplier::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => trim($validated['name']),
            'phone' => $this->trimOrNull($validated['phone'] ?? null),
            'email' => $this->trimOrNull($validated['email'] ?? null),
            'address' => $this->trimOrNull($validated['address'] ?? null),
            'balance' => 0,
            'version' => 1,
        ]);

        return redirect()
            ->route('admin.b.suppliers.index', $business)
            ->with('status', 'Supplier created.');
    }

    public function edit(Request $request, Business $business, Supplier $supplier): View
    {
        abort_if($supplier->business_id !== $business->id, 404);

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        $receiptsTotal = (float) $supplier->purchaseOrders()->sum('total');

        return view('admin.suppliers.edit', $this->workspace($request, $business) + [
            'supplier' => $supplier,
            'currencySymbol' => $currencySymbol,
            'receiptsTotal' => $receiptsTotal,
        ]);
    }

    public function update(Request $request, Business $business, Supplier $supplier): RedirectResponse
    {
        abort_if($supplier->business_id !== $business->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $supplier->name = trim($validated['name']);
        $supplier->phone = $this->trimOrNull($validated['phone'] ?? null);
        $supplier->email = $this->trimOrNull($validated['email'] ?? null);
        $supplier->address = $this->trimOrNull($validated['address'] ?? null);
        $supplier->version = $supplier->version + 1;
        $supplier->save();

        return redirect()
            ->route('admin.b.suppliers.index', $business)
            ->with('status', 'Supplier updated.');
    }

    private function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trim = trim($value);

        return $trim === '' ? null : $trim;
    }

    public function destroy(Request $request, Business $business, Supplier $supplier): RedirectResponse
    {
        abort_if($supplier->business_id !== $business->id, 404);

        if ($supplier->purchaseOrders()->exists()) {
            return redirect()
                ->route('admin.b.suppliers.index', $business)
                ->withErrors(['supplier' => 'Cannot delete this supplier while purchase history exists. Remove or reassign purchases first.']);
        }

        $supplier->delete();

        return redirect()
            ->route('admin.b.suppliers.index', $business)
            ->with('status', 'Supplier deleted.');
    }
}
