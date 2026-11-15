<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** The only place that changes users.points. */
class PointService
{
    /** Types that count towards lifetime (earned) points for group upgrades. */
    public const EARNING_TYPES = ['order', 'referral', 'admin'];

    public function __construct(private WalletService $wallet) {}

    public function add(User $user, int $points, string $type, ?Model $reference = null, ?string $description = null): ?PointTransaction
    {
        if ($points <= 0) {
            return null;
        }

        $tx = $this->apply($user, $points, $type, $reference, $description);
        $this->maybeUpgradeGroup($user);

        return $tx;
    }

    public function subtract(User $user, int $points, string $type, ?Model $reference = null, ?string $description = null): ?PointTransaction
    {
        return $points > 0 ? $this->apply($user, -$points, $type, $reference, $description) : null;
    }

    /** Settings with defaults, in one place for views and logic. */
    public static function rules(): array
    {
        return [
            'per_ks' => (int) setting('points.per_ks', 1000),
            'redeem_rate' => (float) setting('points.redeem_rate', 10),
            'redeem_min' => (int) setting('points.redeem_min', 100),
            'referral_bonus' => (int) setting('points.referral_bonus', 50),
        ];
    }

    /** Convert points to wallet credit (Ks per point from settings). Returns credited Ks. */
    public function redeem(User $user, int $points): float
    {
        $rules = self::rules();

        if ($rules['redeem_rate'] <= 0) {
            throw new InvalidArgumentException('Redeeming points is turned off.');
        }

        if ($points < $rules['redeem_min']) {
            throw new InvalidArgumentException("Minimum {$rules['redeem_min']} points per redeem.");
        }

        $amount = round($points * $rules['redeem_rate'], 2);

        DB::transaction(function () use ($user, $points, $amount) {
            $tx = $this->subtract($user, $points, 'redeem', null, 'Redeemed to wallet');
            $this->wallet->credit($user, $amount, 'points_redeem', $tx, "{$points} points redeemed");
        });

        return $amount;
    }

    /**
     * Called when an order succeeds. If it's the buyer's first successful order and
     * someone referred them, the referrer gets the referral bonus (once per referee).
     */
    public function rewardReferrer(Order $order): void
    {
        $buyer = $order->user;
        $bonus = self::rules()['referral_bonus'];

        if (! $buyer?->referred_by || $bonus <= 0) {
            return;
        }

        $successCount = Order::where('user_id', $buyer->id)->where('status', Order::SUCCESS)->count();
        if ($successCount !== 1) {
            return;
        }

        $referrer = User::find($buyer->referred_by);
        if (! $referrer || ! $referrer->isActive()) {
            return;
        }

        $already = PointTransaction::where('user_id', $referrer->id)
            ->where('type', 'referral')
            ->where('reference_type', $buyer->getMorphClass())
            ->where('reference_id', $buyer->id)
            ->exists();

        if (! $already) {
            $this->add($referrer, $bonus, 'referral', $buyer, "Referral: {$buyer->username} completed their first order");
        }
    }

    /** Points ever earned (orders, referrals, admin gifts) — used for group upgrades. */
    public function lifetime(User $user): int
    {
        return (int) PointTransaction::where('user_id', $user->id)
            ->whereIn('type', self::EARNING_TYPES)
            ->where('points', '>', 0)
            ->sum('points');
    }

    /** Move the user up to the highest group whose threshold they've reached. Never moves down. */
    public function maybeUpgradeGroup(User $user): void
    {
        $groups = UserGroup::whereNotNull('min_points_for_auto_upgrade')
            ->where('min_points_for_auto_upgrade', '>', 0)
            ->orderByDesc('min_points_for_auto_upgrade')->get();

        if ($groups->isEmpty()) {
            return;
        }

        $lifetime = $this->lifetime($user);
        $target = $groups->first(fn ($g) => $lifetime >= $g->min_points_for_auto_upgrade);

        if (! $target || $target->id === $user->user_group_id) {
            return;
        }

        // Only upgrade: group "sort" is the rank (Customer 1 < Reseller 2 < VIP 3).
        // A user an admin put in a higher group is never moved down.
        $current = $user->user_group_id ? UserGroup::find($user->user_group_id) : null;
        if ($current && $current->sort >= $target->sort) {
            return;
        }

        $user->forceFill(['user_group_id' => $target->id])->save();
        ActivityLog::record('user.group_upgraded', $user, ['to' => $target->name, 'lifetime_points' => $lifetime]);
    }

    private function apply(User $user, int $delta, string $type, ?Model $reference, ?string $description): PointTransaction
    {
        return DB::transaction(function () use ($user, $delta, $type, $reference, $description) {
            /** @var User $locked */
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $after = $locked->points + $delta;

            if ($after < 0) {
                throw new InvalidArgumentException('Not enough points.');
            }

            $locked->forceFill(['points' => $after])->save();
            $user->setRawAttributes($locked->getAttributes(), true);

            return PointTransaction::create([
                'user_id' => $locked->id,
                'type' => $type,
                'points' => $delta,
                'points_after' => $after,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
            ]);
        });
    }
}
