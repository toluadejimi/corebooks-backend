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
            ->orderBy('name')
            ->get();

        return view('admin.suppliers.index', $this->workspace($request, $business) + [
            'suppliers' => $suppliers,
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
        ]);

        Supplier::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => trim($validated['name']),
            'phone' => isset($validated['phone']) ? trim((string) $validated['phone']) ?: null : null,
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

        return view('admin.suppliers.edit', $this->workspace($request, $business) + [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Business $business, Supplier $supplier): RedirectResponse
    {
        abort_if($supplier->business_id !== $business->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $supplier->name = trim($validated['name']);
        $supplier->phone = isset($validated['phone']) ? trim((string) $validated['phone']) ?: null : null;
        $supplier->version = $supplier->version + 1;
        $supplier->save();

        return redirect()
            ->route('admin.b.suppliers.index', $business)
            ->with('status', 'Supplier updated.');
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
