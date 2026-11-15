<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceChangeRequest;
use App\Models\Service;
use App\Models\ServiceCostChange;
use App\Services\PricingService;
use App\Services\Suppliers\SupplierManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/** Profit guard: loss-making services, paused sales, price changes waiting for approval, cost history. */
class PricingController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    public function index(): View
    {
        $minProfit = (float) setting('pricing.global_min_profit', 0);
        $pointRate = max(1, (int) setting('points.convert_rate', 10));

        // Lowest selling price per service (default or any group) vs cost
        $lowest = DB::table('services')
            ->leftJoin('service_prices as sp', 'sp.service_id', '=', 'services.id')
            ->groupBy('services.id', 'services.default_price', 'services.cost_price', 'services.price_currency')
            ->select('services.id', 'services.cost_price', 'services.price_currency', DB::raw('LEAST(services.default_price, COALESCE(MIN(sp.price), services.default_price)) AS lowest'));
        if (DB::getDriverName() === 'sqlite') {
            $lowest = DB::table('services')
                ->leftJoin('service_prices as sp', 'sp.service_id', '=', 'services.id')
                ->groupBy('services.id', 'services.default_price', 'services.cost_price', 'services.price_currency')
                ->select('services.id', 'services.cost_price', 'services.price_currency', DB::raw('MIN(services.default_price, COALESCE(MIN(sp.price), services.default_price)) AS lowest'));
        }

        $lowestKs = "l.lowest * CASE WHEN services.price_currency = 'points' THEN {$pointRate} ELSE 1 END";

        $risky = Service::query()
            ->joinSub($lowest, 'l', 'l.id', '=', 'services.id')
            ->where('services.cost_price', '>', 0)
            ->whereRaw("{$lowestKs} - services.cost_price < ?", [max($minProfit, 0.01)])
            ->orderByRaw("{$lowestKs} - services.cost_price")
            ->limit(200)
            ->addSelect(['services.id', 'services.name', 'services.slug', 'services.status', 'services.cost_price', 'services.pricing_mode', 'services.price_currency', 'services.sales_paused', 'services.paused_reason', 'l.lowest'])
            ->selectRaw("{$lowestKs} AS lowest_ks")
            ->get();

        return view('admin.pricing.index', [
            'risky' => $risky,
            'minProfit' => $minProfit,
            'requests' => PriceChangeRequest::with('service:id,name,slug,price_currency')->where('status', 'pending')->latest('id')->get(),
            'changes' => ServiceCostChange::with(['service:id,name,slug', 'changer:id,username'])->latest('id')->paginate(30),
            'paused' => Service::where('sales_paused', true)->get(['id', 'name', 'slug', 'paused_reason', 'cost_price']),
        ]);
    }

    public function approve(Request $request, PriceChangeRequest $priceChange): RedirectResponse
    {
        try {
            $this->pricing->approve($priceChange, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'New prices applied to '.$priceChange->loadMissing('service')->service->name.'.');
    }

    public function reject(Request $request, PriceChangeRequest $priceChange): RedirectResponse
    {
        try {
            $this->pricing->reject($priceChange, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kept the old prices.');
    }

    public function sync(SupplierManager $manager): RedirectResponse
    {
        $parts = [];
        foreach ($this->pricing->syncAll($manager) as $name => $r) {
            $parts[] = $name.': '.(isset($r['error']) ? 'error' : $r['changed'].' changed');
        }

        return back()->with('success', $parts ? 'Supplier costs checked — '.implode(', ', $parts).'.' : 'No active suppliers.');
    }
}
