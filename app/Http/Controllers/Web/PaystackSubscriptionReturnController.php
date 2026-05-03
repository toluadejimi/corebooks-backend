<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PaystackSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaystackSubscriptionReturnController extends Controller
{
    public function __construct(
        private readonly PaystackSubscriptionService $paystack,
    ) {}

    public function show(Request $request): View
    {
        $reference = (string) $request->query('reference', '');
        $ok = $reference !== '' && $this->paystack->verifyRemoteAndActivate($reference);

        return view('paystack.subscription-return', [
            'ok' => $ok,
            'reference' => $reference,
        ]);
    }
}
