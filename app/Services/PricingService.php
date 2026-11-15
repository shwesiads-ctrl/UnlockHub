<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\PriceChangeRequest;
use App\Models\ResellerPrice;
use App\Models\Service;
use App\Models\ServiceCostChange;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Suppliers\SupplierManager;
use App\Support\Notify;
use Illuminate\Support\Facades\DB;

/**
 * Cost tracking, rule-based prices and profit protection.
 *
 * - Every cost change is logged (old → new, supplier currency, rate, source, who).
 * - Services in "auto" pricing get prices from the group rules (cost + percent / fixed Ks,
 *   at least the group's minimum profit, rounded up). "manual" services keep their own prices.
 * - Big automatic moves (≥ pricing.approval_percent) wait in price_change_requests for an admin.
 * - If cost ≥ a selling price the service is flagged; with pricing.loss_action = pause
 *   new orders are refused until it is fixed.
 * Orders already placed keep their own price/cost snapshot, so nothing here changes them.
 */
class PricingService
{
    public const LOSS_PREFIX = 'loss:';

    public static function rounding(): int
    {
        return max(1, (int) setting('pricing.rounding', 100));
    }

    public static function roundUp(float $value, ?int $step = null): float
    {
        $step ??= self::rounding();

        return $step > 1 ? ceil(round($value, 2) / $step) * $step : ceil($value);
    }

    /** Price for one group from a cost. */
    public static function priceFromRule(float $cost, UserGroup $group): float
    {
        $markup = $group->markup_type === 'fixed'
            ? (float) $group->markup_value
            : $cost * (float) $group->markup_value / 100;

        return self::roundUp($cost + max($markup, (float) $group->min_profit));
    }

    /** @return array{default: float, groups: array<int, float>} */
    public function compute(Service $service, ?float $cost = null): array
    {
        $cost ??= (float) $service->cost_price;
        $groups = UserGroup::orderBy('sort')->get();
        $default = $groups->firstWhere('is_default', true) ?? $groups->first();

        $defaultPrice = $default ? self::priceFromRule($cost, $default) : self::roundUp($cost * 1.15);
        $out = ['default' => $this->toServiceUnit($service, $defaultPrice), 'groups' => []];
        foreach ($groups as $g) {
            if (! $default || $g->id !== $default->id) {
                $out['groups'][$g->id] = $this->toServiceUnit($service, self::priceFromRule($cost, $g));
            }
        }

        return $out;
    }

    /** @return array{default: float, groups: array<int, float>} */
    public function current(Service $service): array
    {
        return [
            'default' => (float) $service->default_price,
            'groups' => $service->prices()->pluck('price', 'user_group_id')->map(fn ($p) => (float) $p)->all(),
        ];
    }

    /**
     * Record a new cost (Ks) and react: reprice auto services, check for losses.
     * $supplierCost is in the supplier's currency (null for manual services).
     */
    public function setCost(Service $service, float $cost, ?float $supplierCost, string $source, ?User $by = null): bool
    {
        $service->loadMissing('supplier');
        $cost = round($cost, 2);
        $old = (float) $service->cost_price;
        $oldSupplier = $service->supplier_cost !== null ? (float) $service->supplier_cost : null;

        $changed = abs($old - $cost) >= 0.01 || ($supplierCost !== null && ($oldSupplier === null || abs($oldSupplier - $supplierCost) >= 0.0001));
        if (! $changed) {
            return false;
        }

        DB::transaction(function () use ($service, $cost, $supplierCost, $source, $by, $old, $oldSupplier) {
            ServiceCostChange::create([
                'service_id' => $service->id,
                'supplier_id' => $service->supplier_id,
                'old_cost' => $old,
                'new_cost' => $cost,
                'old_supplier_cost' => $oldSupplier,
                'new_supplier_cost' => $supplierCost,
                'exchange_rate' => $service->supplier?->exchange_rate,
                'source' => $source,
                'changed_by' => $by?->id,
            ]);

            $service->forceFill([
                'cost_price' => $cost,
                'supplier_cost' => $supplierCost ?? $service->supplier_cost,
                'cost_updated_at' => now(),
            ])->save();
        });

        if ($service->pricing_mode === 'auto') {
            $this->reprice($service, $old, approvedBy: null);
        }

        $this->guardLoss($service->fresh());

        return true;
    }

    /**
     * Auto services: compute rule prices. Applied straight away unless the move is bigger
     * than pricing.approval_percent (then it waits). $approvedBy = an admin doing it by hand.
     */
    public function reprice(Service $service, ?float $oldCost = null, ?User $approvedBy = null): ?PriceChangeRequest
    {
        $new = $this->compute($service);
        $old = $this->current($service);

        $pct = $this->maxChangePercent($old, $new);
        if ($pct < 0.005) {
            return null;
        }

        $limit = (float) setting('pricing.approval_percent', 20);

        if (! $approvedBy && $limit > 0 && $pct >= $limit && $old['default'] > 0) {
            PriceChangeRequest::where('service_id', $service->id)->where('status', 'pending')->update(['status' => 'superseded']);
            $req = PriceChangeRequest::create([
                'service_id' => $service->id, 'old_prices' => $old, 'new_prices' => $new,
                'old_cost' => $oldCost, 'new_cost' => $service->cost_price, 'change_percent' => round($pct, 2),
            ]);
            Notify::admin('pricing', '💲 <b>Price change waiting for approval</b>'
                ."\n".Notify::e($service->name).': '.unit_price($old['default'], $service->price_currency ?? 'ks').' → '.unit_price($new['default'], $service->price_currency ?? 'ks').' ('.round($pct, 1).'%)'
                ."\n".route('admin.pricing.index'));

            return $req;
        }

        if ($approvedBy) {
            PriceChangeRequest::where('service_id', $service->id)->where('status', 'pending')->update(['status' => 'superseded']);
        }
        $this->applyPrices($service, $new);
        ActivityLog::record('pricing.auto_reprice', $service, ['from' => $old, 'to' => $new, 'by' => $approvedBy?->username]);

        return null;
    }

    public function approve(PriceChangeRequest $req, User $by): void
    {
        $req->loadMissing('service');
        DB::transaction(function () use ($req, $by) {
            $r = PriceChangeRequest::whereKey($req->id)->lockForUpdate()->firstOrFail();
            if ($r->status !== 'pending') {
                throw new \RuntimeException('Already '.$r->status.'.');
            }
            $this->applyPrices($req->service, $r->new_prices);
            $r->forceFill(['status' => 'approved', 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
            ActivityLog::record('pricing.change_approved', $req->service, ['request' => $r->id, 'to' => $r->new_prices]);
        });
        $this->guardLoss($req->service->fresh());
    }

    public function reject(PriceChangeRequest $req, User $by): void
    {
        $req->loadMissing('service');
        if ($req->status !== 'pending') {
            throw new \RuntimeException('Already '.$req->status.'.');
        }
        $req->forceFill(['status' => 'rejected', 'reviewed_by' => $by->id, 'reviewed_at' => now()])->save();
        ActivityLog::record('pricing.change_rejected', $req->service, ['request' => $req->id]);
        $this->guardLoss($req->service->fresh());
    }

    /** Lowest selling price (any group) vs cost. Pauses or un-pauses sales. */
    public function guardLoss(Service $service): ?string
    {
        $cost = (float) $service->cost_price;
        $prices = $this->current($service);
        $lowest = min(array_merge([$prices['default']], array_values($prices['groups'])));
        if (($service->price_currency ?? 'ks') === 'points') {
            $lowest *= max(1, (int) PointService::rawRules()['convert_rate']);
        }
        $loss = $cost > 0 && $lowest <= $cost;
        $minProfit = (float) setting('pricing.global_min_profit', 0);
        $thin = ! $loss && $minProfit > 0 && $cost > 0 && ($lowest - $cost) < $minProfit;

        $wasLossPaused = $service->sales_paused && str_starts_with((string) $service->paused_reason, self::LOSS_PREFIX);

        if ($loss && setting('pricing.loss_action', 'pause') === 'pause') {
            if (! $wasLossPaused) {
                $reason = self::LOSS_PREFIX.' cost '.money($cost).' ≥ lowest price '.money($lowest);
                $service->forceFill(['sales_paused' => true, 'paused_reason' => $reason])->save();
                ActivityLog::record('pricing.sales_paused', $service, ['cost' => $cost, 'lowest_price' => $lowest]);
                Notify::admin('pricing', '⛔ <b>Orders paused — selling below cost</b>'
                    ."\n".Notify::e($service->name).': cost '.money($cost).', lowest price '.money($lowest)
                    ."\n".route('admin.services.edit', $service));
            }

            return 'paused';
        }

        if ($wasLossPaused) {
            // Fixed (price raised / cost dropped): open again automatically
            $service->forceFill(['sales_paused' => false, 'paused_reason' => null])->save();
            ActivityLog::record('pricing.sales_resumed', $service, ['cost' => $cost, 'lowest_price' => $lowest]);
        }

        return $loss ? 'loss' : ($thin ? 'thin' : null);
    }

    /** Switch all catalog prices together and keep their approximate Ks value. */
    public function switchCurrency(string $from, string $to, User $by, ?int $previousPointRate = null): void
    {
        if ($from === $to) {
            return;
        }

        $rate = $from === 'points'
            ? max(1, $previousPointRate ?? (int) PointService::rawRules()['convert_rate'])
            : max(1, (int) PointService::rawRules()['convert_rate']);
        DB::transaction(function () use ($from, $to, $by, $rate) {
            \App\Models\Setting::put('catalog.price_currency', $to);
            PriceChangeRequest::where('status', 'pending')->update(['status' => 'superseded']);

            $resellerConvert = fn (float $value): float => $from === 'ks'
                ? ($value > 0 ? (float) ceil($value / $rate) : 0)
                : round($value * $rate, 2);
            foreach (ResellerPrice::orderBy('id')->lockForUpdate()->get() as $resellerPrice) {
                $resellerPrice->update(['price' => $resellerConvert((float) $resellerPrice->price)]);
            }

            foreach (Service::orderBy('id')->lockForUpdate()->get() as $service) {
                $prices = $this->current($service);
                $convert = fn (float $value): float => $from === 'ks'
                    ? ($value > 0 ? (float) ceil($value / $rate) : 0)
                    : round($value * $rate, 2);

                $service->forceFill(['price_currency' => $to])->save();
                if ($service->pricing_mode === 'auto') {
                    $this->applyPrices($service, $this->compute($service));
                } else {
                    $this->applyPrices($service, [
                        'default' => $convert($prices['default']),
                        'groups' => array_map($convert, $prices['groups']),
                    ]);
                }
                $this->guardLoss($service->fresh());
            }

            ActivityLog::record('pricing.currency_switched', null, ['from' => $from, 'to' => $to, 'by' => $by->username]);
        });
    }

    /** Keep the approximate Ks value of manual point prices when the point rate changes. */
    public function rebasePointPrices(int $oldRate, int $newRate, User $by): void
    {
        $oldRate = max(1, $oldRate);
        $newRate = max(1, $newRate);
        if ($oldRate === $newRate) {
            return;
        }

        DB::transaction(function () use ($oldRate, $newRate, $by) {
            PriceChangeRequest::where('status', 'pending')->update(['status' => 'superseded']);
            foreach (ResellerPrice::orderBy('id')->lockForUpdate()->get() as $resellerPrice) {
                $value = (float) $resellerPrice->price;
                $resellerPrice->update(['price' => $value > 0 ? (float) ceil($value * $oldRate / $newRate) : 0]);
            }
            foreach (Service::where('price_currency', 'points')->orderBy('id')->lockForUpdate()->get() as $service) {
                if ($service->pricing_mode === 'auto') {
                    $this->applyPrices($service, $this->compute($service));
                } else {
                    $prices = $this->current($service);
                    $convert = fn (float $value): float => $value > 0 ? (float) ceil($value * $oldRate / $newRate) : 0;
                    $this->applyPrices($service, ['default' => $convert($prices['default']), 'groups' => array_map($convert, $prices['groups'])]);
                }
                $this->guardLoss($service->fresh());
            }

            ActivityLog::record('pricing.point_rate_changed', null, ['from' => $oldRate, 'to' => $newRate, 'by' => $by->username]);
        });
    }

    private function toServiceUnit(Service $service, float $ks): float
    {
        if (($service->price_currency ?? 'ks') !== 'points') {
            return $ks;
        }

        $rate = max(1, (int) PointService::rawRules()['convert_rate']);

        return $ks > 0 ? (float) ceil($ks / $rate) : 0;
    }

    /** Pull the supplier's current prices and update costs of linked services. */
    public function syncSupplierCosts(Supplier $supplier, array $list, ?User $by = null): array
    {
        $byId = collect($list)->keyBy('id');
        $rate = (float) $supplier->exchange_rate;
        $changed = 0;
        $missing = 0;

        foreach (Service::with('supplier')->where('supplier_id', $supplier->id)->get() as $service) {
            $s = $byId->get($service->supplier_service_id);
            if (! $s) {
                $missing++;

                continue;
            }
            if ($this->setCost($service, round($s['credit'] * $rate, 2), (float) $s['credit'], 'sync', $by)) {
                $changed++;
            }
        }

        return ['changed' => $changed, 'missing' => $missing];
    }

    /** Exchange rate edited: re-cost services whose supplier price we know. */
    public function rateChanged(Supplier $supplier, ?User $by = null): int
    {
        $n = 0;
        foreach (Service::with('supplier')->where('supplier_id', $supplier->id)->whereNotNull('supplier_cost')->get() as $service) {
            if ($this->setCost($service, round((float) $service->supplier_cost * (float) $supplier->exchange_rate, 2), (float) $service->supplier_cost, 'rate', $by)) {
                $n++;
            }
        }

        return $n;
    }

    public function syncAll(SupplierManager $manager): array
    {
        $result = [];
        foreach (Supplier::where('status', 'active')->get() as $supplier) {
            try {
                $result[$supplier->name] = $this->syncSupplierCosts($supplier, $manager->driverFor($supplier)->serviceList($supplier));
            } catch (\Throwable $e) {
                report($e);
                $result[$supplier->name] = ['error' => $e->getMessage()];
            }
        }

        return $result;
    }

    private function applyPrices(Service $service, array $prices): void
    {
        $service->forceFill(['default_price' => $prices['default']])->save();
        foreach ((array) ($prices['groups'] ?? []) as $groupId => $price) {
            $service->prices()->updateOrCreate(['user_group_id' => (int) $groupId], ['price' => $price]);
        }
    }

    private function maxChangePercent(array $old, array $new): float
    {
        $max = 0.0;
        $pairs = [[$old['default'], $new['default']]];
        foreach ($new['groups'] as $gid => $p) {
            $pairs[] = [$old['groups'][$gid] ?? $old['default'], $p];
        }
        foreach ($pairs as [$a, $b]) {
            $max = max($max, $a > 0 ? abs($b - $a) / $a * 100 : ($b > 0 ? 100 : 0));
        }

        return $max;
    }
}
