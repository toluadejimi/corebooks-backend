<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Services\SprintPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicShopController extends Controller
{
    public function __construct(
        private readonly SprintPayService $sprintPay,
    ) {}

    public function index(Business $business): View
    {
        abort_unless($business->public_shop_enabled, 404);

        $products = Product::query()
            ->where('business_id', $business->id)
            ->where('available_online', true)
            ->orderBy('name')
            ->get();

        return view('public.shop-index', [
            'business' => $business,
            'products' => $products,
        ]);
    }

    public function product(Business $business, Product $product): View
    {
        abort_if((int) $product->business_id !== (int) $business->id, 404);
        abort_unless($product->available_online, 404);

        return view('public.shop-product', [
            'business' => $business,
            'product' => $product,
        ]);
    }

    public function checkout(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'product_uuid' => ['required', 'uuid'],
            'qty' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'customer_email' => ['required', 'email', 'max:255'],
        ]);

        $product = Product::query()
            ->where('business_id', $business->id)
            ->where('uuid', $data['product_uuid'])
            ->firstOrFail();

        abort_unless($product->available_online, 404);

        $qty = (float) $data['qty'];
        $amount = round($qty * (float) $product->selling_price, 2);
        if ($amount <= 0) {
            return back()->withErrors(['amount' => 'Invalid amount.'])->withInput();
        }

        $reference = 'web-'.$business->uuid.'-'.$product->uuid.'-'.bin2hex(random_bytes(4));
        $callback = url('/shop/'.$business->uuid.'/thanks?ref='.urlencode($reference));

        $payload = [
            'amount' => $amount,
            'currency' => strtoupper((string) ($business->currency ?? 'NGN')),
            'externalReference' => $reference,
            'callbackUrl' => $callback,
            'customerEmail' => $data['customer_email'],
            'description' => $product->name.' × '.$qty,
        ];

        $payUrl = $this->sprintPay->requestHostedCardUrl($payload);

        if ($payUrl === null) {
            return back()
                ->withErrors(['payment' => 'Online card payment is not configured yet. Add SprintPay keys in .env (see .env.example).'])
                ->withInput();
        }

        return redirect()->away($payUrl);
    }

    public function thanks(Request $request, Business $business): View
    {
        return view('public.shop-thanks', [
            'business' => $business,
            'ref' => $request->query('ref'),
        ]);
    }

    public function resolveBySlug(string $slug): RedirectResponse
    {
        $business = Business::query()->where('public_shop_slug', $slug)->firstOrFail();
        abort_unless($business->public_shop_enabled, 404);

        return redirect()->route('public.shop', $business);
    }
}
