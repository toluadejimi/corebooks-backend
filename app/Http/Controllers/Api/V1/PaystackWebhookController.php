<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaystackSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaystackSubscriptionService $paystack,
    ) {}

    public function handle(Request $request): Response
    {
        $signature = $request->header('x-paystack-signature');
        $raw = $request->getContent();

        if (! $this->paystack->verifySignature($raw, $signature)) {
            return response('invalid signature', 400);
        }

        $event = $request->input('event');
        if ($event !== 'charge.success') {
            return response('ignored', 200);
        }

        $reference = data_get($request->all(), 'data.reference');
        if (! is_string($reference) || $reference === '') {
            return response('no reference', 200);
        }

        $this->paystack->activateFromSuccessfulCharge($reference);

        return response('ok', 200);
    }
}
