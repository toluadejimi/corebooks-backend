<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Location;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BusinessSettingsWebController extends Controller
{
    use ResolvesWorkspace;

    public function edit(Request $request, Business $business): View
    {
        $business->load(['locations' => fn ($q) => $q->orderByDesc('is_default')->orderBy('name')]);

        return view('admin.settings.edit', $this->workspace($request, $business) + [
            'biz' => $business,
            'receiptFooter' => data_get($business->settings, 'receipt_footer'),
        ]);
    }

    public function updateProfile(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['nullable', 'string', 'max:8'],
            'default_vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:64'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
        ]);

        $hadReceiptFooter = array_key_exists('receipt_footer', $data);
        $footer = Arr::pull($data, 'receipt_footer');
        if ($hadReceiptFooter) {
            $settings = $business->settings ?? [];
            if ($footer === null || $footer === '') {
                unset($settings['receipt_footer']);
            } else {
                $settings['receipt_footer'] = $footer;
            }
            $business->settings = $settings;
        }

        if (($data['country'] ?? '') === '') {
            $data['country'] = 'NG';
        }
        if (($data['currency'] ?? '') === '') {
            $data['currency'] = 'NGN';
        }

        $business->fill($data);
        $business->country = strtoupper((string) ($business->country ?? 'NG'));
        $business->currency = strtoupper((string) ($business->currency ?? 'NGN'));
        $business->version = (int) $business->version + 1;
        $business->save();

        return redirect()
            ->route('admin.b.settings.edit', $business)
            ->with('status', 'Business profile saved.');
    }

    public function uploadLogo(Request $request, Business $business): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ]);

        $file = $request->file('image');
        $path = $file->store("businesses/{$business->id}/images", 'public');

        $relative = Storage::disk('public')->url($path);
        $url = str_starts_with($relative, 'http')
            ? $relative
            : rtrim($request->root() ?: (string) config('app.url'), '/').'/'.ltrim($relative, '/');

        return redirect()
            ->route('admin.b.settings.edit', $business)
            ->with('status', 'Logo uploaded.')
            ->with('logo_uploaded_url', $url);
    }

    public function storeLocation(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        Location::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'is_default' => false,
            'version' => 1,
        ]);

        return redirect()
            ->route('admin.b.settings.edit', $business)
            ->with('status', 'Branch added.');
    }

    public function destroyLocation(Request $request, Business $business, Location $location): RedirectResponse
    {
        abort_if($location->business_id !== $business->id, 404);

        if ($business->locations()->count() <= 1) {
            return redirect()
                ->route('admin.b.settings.edit', $business)
                ->withErrors(['location' => 'You must keep at least one branch.']);
        }

        if ($location->is_default) {
            return redirect()
                ->route('admin.b.settings.edit', $business)
                ->withErrors(['location' => 'Set another branch as default before deleting this one.']);
        }

        if (Sale::query()->where('business_id', $business->id)->where('location_id', $location->id)->exists()) {
            return redirect()
                ->route('admin.b.settings.edit', $business)
                ->withErrors(['location' => 'Cannot delete a branch that has historical sales.']);
        }

        if (ProductBatch::query()->where('business_id', $business->id)->where('location_id', $location->id)->where('qty', '>', 0)->exists()) {
            return redirect()
                ->route('admin.b.settings.edit', $business)
                ->withErrors(['location' => 'Transfer or sell remaining stock before deleting this branch.']);
        }

        if (PurchaseOrder::query()->where('business_id', $business->id)->where('location_id', $location->id)->exists()) {
            return redirect()
                ->route('admin.b.settings.edit', $business)
                ->withErrors(['location' => 'Cannot delete a branch referenced by purchase records.']);
        }

        $location->delete();

        return redirect()
            ->route('admin.b.settings.edit', $business)
            ->with('status', 'Branch removed.');
    }
}
