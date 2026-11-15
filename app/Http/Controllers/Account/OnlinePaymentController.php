<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\OnlinePayment;
use App\Services\Payments\OnlinePaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OnlinePaymentController extends Controller
{
    public function __construct(private OnlinePaymentService $payments) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gateway' => ['required', 'in:stripe,paypal'],
            'amount' => ['required', 'numeric', 'min:0.5', 'max:100000'],
        ]);

        try {
            $url = $this->payments->start($request->user(), $data['gateway'], (float) $data['amount']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }

    /** Back from Stripe / PayPal: confirm with the gateway (never trust the URL alone). */
    public function return(Request $request): RedirectResponse
    {
        $p = $this->own($request);
        $p = $this->payments->sync($p);

        return redirect()->route('account.funds.index')->with(...match ($p->status) {
            'paid' => ['success', 'Payment confirmed — '.money($p->credit_ks).' added to your wallet.'],
            'pending' => ['info', 'Payment received by '.OnlinePayment::GATEWAYS[$p->gateway].' and waiting for confirmation. Your wallet updates automatically within a few minutes.'],
            default => ['error', 'The payment was not completed ('.$p->status.'). Nothing was charged to your wallet.'],
        });
    }

    public function cancel(Request $request): RedirectResponse
    {
        $p = $this->payments->sync($this->own($request)); // paid after all? then it is credited

        if ($p->status === 'pending') {
            $p->forceFill(['status' => 'cancelled'])->save();
        }

        return redirect()->route('account.funds.index')->with($p->status === 'paid' ? 'success' : 'error',
            $p->status === 'paid' ? 'Payment confirmed — '.money($p->credit_ks).' added.' : 'Payment cancelled. Nothing was charged.');
    }

    private function own(Request $request): OnlinePayment
    {
        $p = OnlinePayment::findOrFail((int) $request->query('payment'));
        abort_unless($p->user_id === $request->user()->id, 404);

        return $p;
    }
}
