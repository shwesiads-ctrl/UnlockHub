<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\ResellerPrice;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resellers sell to their own customers (sub-users).
 *
 * Money: a sub-user's wallet is funded by its reseller (transfer). When a sub-user orders,
 * the sub-user pays the reseller's selling price; the site keeps the reseller's own price
 * (orders.reseller_price); the difference is credited to the reseller as profit when the
 * order succeeds. Rejected orders refund the sub-user and pay no profit.
 */
class ResellerService
{
    public function __construct(private WalletService $wallet) {}

    public static function enabled(): bool
    {
        return setting('reseller.enabled', '1') === '1';
    }

    /** Create the initial approved profile when an admin assigns the Reseller price group. */
    public function provisionFromResellerGroup(User $user): ?ResellerProfile
    {
        if (! $user->isActive() || $user->parent_id || ! $user->isResellerGroup()) {
            return null;
        }

        return ResellerProfile::firstOrCreate(['user_id' => $user->id], [
            'status' => 'approved',
            'shop_name' => mb_substr(trim((string) ($user->shop_name ?: $user->name)).' Shop', 0, 80),
            'contact_phone' => $user->phone,
            'contact_email' => $user->email,
            'markup_type' => 'percent',
            'markup_value' => (float) setting('reseller.default_markup', 10),
            'approved_at' => now(),
        ]);
    }

    /** What the reseller pays the site (their own group price). */
    public static function costFor(Service $service, User $reseller): float
    {
        return (float) $service->priceFor($reseller);
    }

    /** What the reseller's customer pays: own price if set (never below cost), else cost + markup. */
    public static function sellPrice(Service $service, User $reseller): float
    {
        $cost = self::costFor($service, $reseller);
        $own = ResellerPrice::where('reseller_id', $reseller->id)->where('service_id', $service->id)->value('price');
        if ($own !== null) {
            return max((float) $own, $cost);
        }

        $p = $reseller->resellerProfile;
        $pointsCurrency = ($service->price_currency ?? 'ks') === 'points';
        $markup = $p?->markup_type === 'fixed' ? (float) $p->markup_value : $cost * (float) ($p?->markup_value ?? 0) / 100;
        if ($pointsCurrency && $p?->markup_type === 'fixed') {
            $markup = ceil($markup / max(1, (int) PointService::rawRules()['convert_rate']));
        }

        return PricingService::roundUp($cost + max(0, $markup), $pointsCurrency ? 1 : max(1, (int) setting('reseller.rounding', 10)));
    }

    /** On success: credit the reseller's profit once. */
    public function payProfit(Order $order): void
    {
        if (! $order->reseller_id || $order->reseller_paid || $order->reseller_price === null) {
            return;
        }

        $profit = round((float) $order->price - (float) $order->reseller_price, 2);
        if (($order->price_currency ?? 'ks') === 'points') {
            $profit *= max(1, (int) ($order->price_rate ?: PointService::rawRules()['convert_rate']));
        }
        $reseller = User::find($order->reseller_id);

        if ($profit > 0 && $reseller) {
            $this->wallet->credit($reseller, $profit, 'reseller_profit', $order, "Profit · order #{$order->order_no}");
        }
        $order->forceFill(['reseller_paid' => true])->save();
    }

    public function createCustomer(User $reseller, array $data): User
    {
        $customer = new User([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'user_group_id' => \App\Models\UserGroup::where('is_default', true)->value('id'),
        ]);
        $customer->forceFill(['parent_id' => $reseller->id, 'email_verified_at' => now()])->save();
        ActivityLog::record('reseller.customer_created', $customer, ['reseller' => $reseller->username]);

        return $customer;
    }

    /** Move money between a reseller and one of their customers. $amount > 0 = to the customer. */
    public function transfer(User $reseller, User $customer, float $amount, ?string $note = null): void
    {
        if ($customer->parent_id !== $reseller->id) {
            throw new RuntimeException('Not your customer.');
        }
        $amount = round($amount, 2);
        if ($amount == 0.0) {
            throw new RuntimeException('Enter an amount.');
        }

        DB::transaction(function () use ($reseller, $customer, $amount, $note) {
            $note = $note ? ' · '.mb_substr($note, 0, 80) : '';
            if ($amount > 0) {
                $this->wallet->debit($reseller, $amount, 'transfer_out', $customer, "Funds to {$customer->username}{$note}");
                $this->wallet->credit($customer, $amount, 'transfer_in', $reseller, "Funds from {$reseller->resellerProfile?->shop_name}{$note}");
            } else {
                $this->wallet->debit($customer, -$amount, 'transfer_out', $reseller, "Returned to {$reseller->resellerProfile?->shop_name}{$note}");
                $this->wallet->credit($reseller, -$amount, 'transfer_in', $customer, "Taken back from {$customer->username}{$note}");
            }
        });
        ActivityLog::record('reseller.transfer', $customer, ['reseller' => $reseller->username, 'amount' => $amount]);
    }
}
