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
    public const EARNING_TYPES = ['order', 'referral', 'admin', 'signup', 'profile', 'topup', 'checkin'];

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
            'signup_bonus' => (int) setting('points.signup_bonus', 20),
            'profile_bonus' => (int) setting('points.profile_bonus', 30),
            'topup_per_ks' => (int) setting('points.topup_per_ks', 0),       // 1 point per X Ks topped up, 0 = off
            'checkin_points' => (int) setting('points.checkin_points', 1),
            'checkin_streak_bonus' => (int) setting('points.checkin_streak_bonus', 1), // extra per streak day
            'checkin_streak_max' => (int) setting('points.checkin_streak_max', 7),     // streak bonus stops growing here
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
            \App\Support\Notify::user($referrer, new \App\Notifications\PointsReceived($bonus, "Your friend {$buyer->username} completed their first order."));
        }
    }

    /** Welcome points, once per user. */
    public function awardSignup(User $user): void
    {
        $bonus = self::rules()['signup_bonus'];

        if ($bonus > 0 && ! PointTransaction::where('user_id', $user->id)->where('type', 'signup')->exists()) {
            $this->add($user, $bonus, 'signup', null, 'Welcome bonus');
        }
    }

    /** Once, when email, phone, photo, shop name and city are all filled. Returns points given (0 = none). */
    public function maybeAwardProfileBonus(User $user): int
    {
        $bonus = self::rules()['profile_bonus'];

        if ($bonus <= 0 || $user->profile_bonus_awarded || in_array(false, $user->profileChecklist(), true)) {
            return 0;
        }

        DB::transaction(function () use ($user, $bonus) {
            $user->forceFill(['profile_bonus_awarded' => true])->save();
            $this->add($user, $bonus, 'profile', null, 'Profile completed');
        });

        return $bonus;
    }

    /** Points for an approved top-up (e.g. 1 point per 10,000 Ks). */
    public function awardTopup(\App\Models\PaymentRequest $request): int
    {
        $perKs = self::rules()['topup_per_ks'];
        $points = $perKs > 0 ? intdiv((int) $request->credited_amount, $perKs) : 0;

        if ($points > 0) {
            $this->add($request->user, $points, 'topup', $request, 'Top-up bonus · '.money($request->credited_amount));
        }

        return $points;
    }

    /**
     * Daily check-in. Consecutive days build a streak: base + bonus × (streak − 1), capped.
     *
     * @return array{points: int, streak: int}
     */
    public function checkIn(User $user): array
    {
        $rules = self::rules();

        if ($rules['checkin_points'] <= 0) {
            throw new InvalidArgumentException('Daily check-in is turned off.');
        }

        return DB::transaction(function () use ($user, $rules) {
            /** @var User $locked */
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $today = today();

            if ($locked->last_checkin_on?->isSameDay($today)) {
                throw new InvalidArgumentException('You already checked in today. Come back tomorrow!');
            }

            $streak = $locked->last_checkin_on?->isSameDay($today->copy()->subDay()) ? $locked->checkin_streak + 1 : 1;
            $extraDays = min($streak, max(1, $rules['checkin_streak_max'])) - 1;
            $points = $rules['checkin_points'] + $extraDays * max(0, $rules['checkin_streak_bonus']);

            $locked->forceFill(['last_checkin_on' => $today, 'checkin_streak' => $streak])->save();
            $user->forceFill(['last_checkin_on' => $today, 'checkin_streak' => $streak]);

            $this->add($user, $points, 'checkin', null, "Daily check-in · day {$streak}");

            return ['points' => $points, 'streak' => $streak];
        });
    }

    public function canCheckInToday(User $user): bool
    {
        return self::rules()['checkin_points'] > 0 && ! $user->last_checkin_on?->isSameDay(today());
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
