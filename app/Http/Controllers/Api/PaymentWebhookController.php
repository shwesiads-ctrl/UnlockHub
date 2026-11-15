<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnlinePayment;
use App\Services\Payments\OnlinePaymentService;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gateway webhooks only tell us WHICH payment to look at. The result is always read back from
 * the gateway's API (sync), so a forged webhook can't credit anything.
 */
class PaymentWebhookController extends Controller
{
    public function stripe(Request $request, OnlinePaymentService $payments): JsonResponse
    {
        if (! StripeGateway::validSignature($request->getContent(), (string) $request->header('Stripe-Signature'))) {
            return response()->json(['error' => 'bad signature'], 400);
        }

        $object = $request->json('data.object', []);
        if (in_array($request->json('type'), ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.expired'], true)) {
            if ($p = OnlinePayment::where('gateway', 'stripe')->where('reference', (string) ($object['id'] ?? ''))->first()) {
                $payments->sync($p);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function paypal(Request $request, OnlinePaymentService $payments): JsonResponse
    {
        $resource = $request->json('resource', []);
        $orderId = match ($request->json('event_type')) {
            'CHECKOUT.ORDER.APPROVED', 'CHECKOUT.ORDER.COMPLETED' => $resource['id'] ?? null,
            'PAYMENT.CAPTURE.COMPLETED' => $resource['supplementary_data']['related_ids']['order_id'] ?? null,
            default => null,
        };

        if ($orderId && ($p = OnlinePayment::where('gateway', 'paypal')->where('reference', (string) $orderId)->first())) {
            $payments->sync($p);
        }

        return response()->json(['ok' => true]);
    }
}
