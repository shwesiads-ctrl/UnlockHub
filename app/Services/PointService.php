<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** The only place that changes users.points. */
class PointService
{
    public function __construct(private WalletService $wallet) {}

    public function add(User $user, int $points, string $type, ?Model $reference = null, ?string $description = null): ?PointTransaction
    {
        return $points > 0 ? $this->apply($user, $points, $type, $reference, $description) : null;
    }

    public function subtract(User $user, int $points, string $type, ?Model $reference = null, ?string $description = null): ?PointTransaction
    {
        return $points > 0 ? $this->apply($user, -$points, $type, $reference, $description) : null;
    }

    /** Convert points to wallet credit using settings points.redeem_rate (Ks per point). */
    public function redeem(User $user, int $points): void
    {
        $min = (int) setting('points.redeem_min', 100);
        $rate = (float) setting('points.redeem_rate', 10);

        if ($points < $min) {
            throw new InvalidArgumentException("Minimum {$min} points.");
        }

        DB::transaction(function () use ($user, $points, $rate) {
            $tx = $this->subtract($user, $points, 'redeem', null, 'Redeemed to wallet');
            $this->wallet->credit($user, round($points * $rate, 2), 'points_redeem', $tx, "{$points} points redeemed");
        });
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
