<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductWebController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request, Business $business): View
    {
        $products = Product::query()
            ->where('business_id', $business->id)
            ->with(['category:id,name'])
            ->withSum('batches', 'qty')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.products.index', $this->workspace($request, $business) + [
            'products' => $products,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    public function create(Request $request, Business $business): View
    {
        return view('admin.products.form', $this->workspace($request, $business) + [
            'product' => null,
            'locations' => $business->locations()->orderByDesc('is_default')->get(),
            'categories' => Category::query()->where('business_id', $business->id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:128'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $business->id)],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'initial_qty' => ['nullable', 'numeric', 'min:0'],
            'location_uuid' => ['nullable', 'uuid'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $location = $business->locations()->where('is_default', true)->first()
            ?? $business->locations()->firstOrFail();
        if (! empty($data['location_uuid'])) {
            $location = $business->locations()->where('uuid', $data['location_uuid'])->firstOrFail();
        }

        $product = Product::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'image_url' => isset($data['image_url']) && $data['image_url'] !== '' ? $data['image_url'] : null,
            'cost_price' => $data['cost_price'] ?? 0,
            'selling_price' => $data['selling_price'] ?? 0,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? 0,
            'track_batches' => false,
            'vat_rate' => $data['vat_rate'] ?? $business->default_vat_rate,
            'version' => 1,
        ]);

        ProductBatch::query()->create([
            'business_id' => $business->id,
            'product_id' => $product->id,
            'location_id' => $location->id,
            'uuid' => (string) Str::uuid(),
            'qty' => (float) ($data['initial_qty'] ?? 0),
            'expiry_date' => $data['expiry_date'] ?? null,
            'cost_price_snapshot' => $product->cost_price,
            'version' => 1,
        ]);

        return redirect()
            ->route('admin.b.products.index', $business)
            ->with('status', 'Product created.');
    }

    /**
     * GET /products/{uuid} — canonical edit URL uses /edit; this avoids 404 when the bare URL is opened or GET-followed.
     */
    public function redirectToEdit(Request $request, Business $business, Product $product): RedirectResponse
    {
        abort_if($product->business_id !== $business->id, 404);

        return redirect()->route('admin.b.products.edit', [$business, $product->uuid]);
    }

    public function edit(Request $request, Business $business, Product $product): View
    {
        abort_if($product->business_id !== $business->id, 404);
        $product->loadSum('batches', 'qty');

        return view('admin.products.form', $this->workspace($request, $business) + [
            'product' => $product,
            'locations' => $business->locations()->orderByDesc('is_default')->get(),
            'categories' => Category::query()->where('business_id', $business->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Business $business, Product $product): RedirectResponse
    {
        abort_if($product->business_id !== $business->id, 404);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:128'],
            'barcode' => ['nullable', 'string', 'max:128'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('business_id', $business->id)],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if (array_key_exists('image_url', $data) && ($data['image_url'] === '' || $data['image_url'] === null)) {
            $data['image_url'] = null;
        }

        $keys = ['name', 'sku', 'barcode', 'category_id', 'image_url', 'cost_price', 'selling_price', 'low_stock_threshold', 'vat_rate'];
        $product->fill(array_intersect_key($data, array_flip($keys)));
        $product->version = $product->version + 1;
        $product->save();

        return redirect()
            ->route('admin.b.products.index', $business)
            ->with('status', 'Product updated.');
    }

    public function destroy(Request $request, Business $business, Product $product): RedirectResponse
    {
        abort_if($product->business_id !== $business->id, 404);
        $product->delete();

        return redirect()
            ->route('admin.b.products.index', $business)
            ->with('status', 'Product removed.');
    }
}
