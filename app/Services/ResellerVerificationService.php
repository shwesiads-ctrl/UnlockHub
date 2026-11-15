<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\ResellerProfile;
use Illuminate\Support\Facades\DB;

class ResellerVerificationService
{
    /** Verify an approved reseller once their successful customer sales reach the Ks threshold. */
    public function verifyIfEligible(int $resellerId): bool
    {
        if (setting('reseller.auto_verify_enabled', '0') !== '1') {
            return false;
        }

        $threshold = max(1, (float) setting('reseller.auto_verify_amount', 1000000));
        $pointRate = max(1, (int) setting('points.convert_rate', 10));

        return DB::transaction(function () use ($resellerId, $threshold, $pointRate) {
            $profile = ResellerProfile::where('user_id', $resellerId)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->first();

            if (! $profile || $profile->is_verified || ! $profile->user?->isActive()) {
                return false;
            }

            $sales = (float) Order::where('reseller_id', $resellerId)
                ->where('status', Order::SUCCESS)
                ->selectRaw("COALESCE(SUM(CASE WHEN price_currency = 'points' THEN price * COALESCE(price_rate, ?) ELSE price END), 0) AS total", [$pointRate])
                ->value('total');

            if ($sales < $threshold) {
                return false;
            }

            $profile->forceFill([
                'is_verified' => true,
                'verified_at' => now(),
                'verification_source' => 'automatic',
            ])->save();

            ActivityLog::record('reseller.auto_verified', $profile->user, ['sales_ks' => $sales, 'threshold_ks' => $threshold]);

            return true;
        });
    }

    /** Apply a newly enabled or lowered threshold to existing approved resellers. */
    public function verifyEligibleResellers(): int
    {
        if (setting('reseller.auto_verify_enabled', '0') !== '1') {
            return 0;
        }

        $verified = 0;
        ResellerProfile::where('status', 'approved')->orderBy('user_id')->pluck('user_id')
            ->each(function ($id) use (&$verified) {
                if ($this->verifyIfEligible((int) $id)) {
                    $verified++;
                }
            });

        return $verified;
    }
}
