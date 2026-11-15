<?php

namespace App\Services\Payments;

use App\Models\ActivityLog;
use App\Models\OnlinePayment;
use App\Models\User;
use App\Notifications\OnlineTopupReceived;
use App\Services\PointService;
use App\Services\WalletService;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Card / PayPal top-ups. The wallet is credited ONLY after the gateway's own API says the
 * money arrived (return page, webhook or the 5-minute sweep all call sync()), the amount and
 * currency match what we asked for, and only once (row lock + status check).
 */
class OnlinePaymentService
{
    public function __construct(private StripeGateway $stripe, private PayPalGateway $paypal, private WalletService $wallet) {}

    public static function config(): array
    {
        return [
            'currency' => strtoupper((string) setting('payments.online.currency', 'USD')),
            'rate' => (float) setting('payments.online.rate', 4500),          // Ks per 1 unit
            'min' => (float) setting('payments.online.min', 1),
            'max' => (float) setting('payments.online.max', 500),
            'fee_percent' => (float) setting('payments.online.fee_percent', 0),
        ];
    }

    /** @return list<string> */
    public static function gateways(): array
    {
        return array_keys(array_filter(['stripe' => StripeGateway::enabled(), 'paypal' => PayPalGateway::enabled()]));
    }

    /** Create the payment and return the URL to send the customer to. */
    public function start(User $user, string $gateway, float $amount): string
    {
        $c = self::config();
        if (! in_array($gateway, self::gateways(), true)) {
            throw new RuntimeException('This payment method is not available.');
        }
        if ($c['rate'] <= 0) {
            throw new RuntimeException('Online top-up is not configured yet.');
        }
        if ($amount < $c['min'] || $amount > $c['max']) {
            throw new RuntimeException("Amount must be between {$c['min']} and {$c['max']} {$c['currency']}.");
        }
        if (OnlinePayment::where('user_id', $user->id)->where('status', 'pending')->where('created_at', '>', now()->subHour())->count() >= 5) {
            throw new RuntimeException('You have several unfinished payments. Please finish or wait an hour.');
        }

        $amount = round($amount, 2);
        $fee = round($amount * $c['fee_percent'] / 100, 2);
        $p = OnlinePayment::create([
            'user_id' => $user->id, 'gateway' => $gateway, 'amount' => $amount + $fee, 'fee' => $fee,
            'currency' => $c['currency'], 'rate' => $c['rate'], 'credit_ks' => round($amount * $c['rate'], 2), 'status' => 'pending',
        ]);

        $back = route('account.online.return', ['payment' => $p->id]);
        $cancel = route('account.online.cancel', ['payment' => $p->id]);

        try {
            $r = $gateway === 'stripe'
                ? $this->stripe->createCheckout($p, $back.'&session_id={CHECKOUT_SESSION_ID}', $cancel, $user->email)
                : $this->paypal->createOrder($p, $back, $cancel);
        } catch (\Throwable $e) {
            $p->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)])->save();
            report($e);
            throw new RuntimeException('Could not start the payment. Please try again later.');
        }

        $p->forceFill(['reference' => $r['id']])->save();

        return $r['url'];
    }

    /** Ask the gateway what happened, credit if paid. Safe to call any number of times. */
    public function sync(OnlinePayment $p): OnlinePayment
    {
        if ($p->status !== 'pending' || ! $p->reference) {
            return $p;
        }

        try {
            if ($p->gateway === 'stripe') {
                $s = $this->stripe->status($p->reference);
                if ($s['paid']) {
                    $this->markPaid($p, $s['amount'], $s['currency'], $s['txn']);
                } elseif ($s['expired']) {
                    $p->forceFill(['status' => 'expired'])->save();
                }
            } else {
                $s = $this->paypal->settle($p->reference);
                if ($s['paid']) {
                    $this->markPaid($p, $s['amount'], $s['currency'], $s['txn']);
                } elseif (in_array($s['status'], ['VOIDED'], true)) {
                    $p->forceFill(['status' => 'failed', 'error' => 'Order voided'])->save();
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        if ($p->status === 'pending' && $p->created_at->lt(now()->subDay())) {
            $p->forceFill(['status' => 'expired'])->save();
        }

        return $p->fresh();
    }

    private function markPaid(OnlinePayment $payment, float $amount, string $currency, ?string $txn): void
    {
        $credited = DB::transaction(function () use ($payment, $amount, $currency, $txn) {
            $p = OnlinePayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->status !== 'pending') {
                return false; // already handled (webhook + return page racing)
            }

            if (abs($amount - (float) $p->amount) > 0.009 || strtoupper($currency) !== strtoupper($p->currency)) {
                $p->forceFill(['status' => 'failed', 'gateway_txn' => $txn, 'error' => "Paid {$amount} {$currency}, expected {$p->amount} {$p->currency}"])->save();
                Notify::admin('payment', '⚠️ <b>Online payment amount mismatch</b> #'.$p->id."\n".Notify::e($p->error)."\nCheck it in the {$p->gateway} dashboard.");

                return false;
            }

            $p->loadMissing('user');
            $this->wallet->credit($p->user, (float) $p->credit_ks, 'deposit', $p,
                'Top-up via '.OnlinePayment::GATEWAYS[$p->gateway].' ('.number_format((float) $p->amount, 2).' '.$p->currency.($txn ? ' · '.$txn : '').')');
            $p->forceFill(['status' => 'paid', 'gateway_txn' => $txn, 'paid_at' => now()])->save();

            app(PointService::class)->awardTopupFor($p->user, (float) $p->credit_ks, $p);
            ActivityLog::record('payment.online_paid', $p, ['gateway' => $p->gateway, 'amount' => (float) $p->amount, 'credit_ks' => (float) $p->credit_ks]);

            return $p;
        });

        if ($credited) {
            Notify::user($credited->user, new OnlineTopupReceived($credited));
            Notify::admin('payment', '💳 <b>Online top-up</b> '.OnlinePayment::GATEWAYS[$credited->gateway]
                ."\n".Notify::e($credited->user->username).' · '.number_format((float) $credited->amount, 2).' '.$credited->currency.' → '.money($credited->credit_ks));
            $payment->refresh();
        }
    }
}
