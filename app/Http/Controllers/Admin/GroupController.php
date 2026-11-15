<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** User groups = price tiers (Customer, Reseller, VIP…). "Sort" is the rank, low → high. */
class GroupController extends Controller
{
    public function index(): View
    {
        return view('admin.groups.index', [
            'groups' => UserGroup::withCount(['users', 'prices'])->orderBy('sort')->orderBy('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:user_groups,name'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = Str::slug($data['name']) ?: Str::lower(Str::random(6));
        while (UserGroup::where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(3));
        }

        UserGroup::create([
            'name' => $data['name'],
            'slug' => $slug,
            'sort' => $data['sort'] ?? ((int) UserGroup::max('sort') + 1),
        ]);

        return back()->with('success', "Group \"{$data['name']}\" added. Set its prices in each service.");
    }

    public function update(Request $request, UserGroup $group): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:user_groups,name,'.$group->id],
            'sort' => ['required', 'integer', 'min:0'],
            'markup_type' => ['required', 'in:percent,fixed'],
            'markup_value' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'min_profit' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $before = $group->only(['markup_type', 'markup_value', 'min_profit']);
        $group->update($data);
        if ($before != $group->only(['markup_type', 'markup_value', 'min_profit'])) {
            ActivityLog::record('group.pricing_rule', $group, ['from' => $before, 'to' => $group->only(['markup_type', 'markup_value', 'min_profit'])]);
        }

        return back()->with('success', 'Group saved. Use "Reprice auto services" to apply new rules.');
    }

    public function pricingSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'rounding' => ['required', 'in:1,10,50,100,500,1000'],
            'loss_action' => ['required', 'in:warn,pause'],
            'approval_percent' => ['required', 'numeric', 'min:0', 'max:1000'],
            'global_min_profit' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        foreach ($data as $k => $v) {
            \App\Models\Setting::put('pricing.'.$k, (string) $v);
        }
        ActivityLog::record('pricing.settings', null, $data);

        return back()->with('success', 'Pricing settings saved.');
    }

    /** Recompute prices of every auto-priced service from the current rules (admin = approval). */
    public function repriceAll(Request $request, \App\Services\PricingService $pricing): RedirectResponse
    {
        $n = 0;
        foreach (\App\Models\Service::where('pricing_mode', 'auto')->get() as $service) {
            $pricing->reprice($service, null, approvedBy: $request->user());
            $pricing->guardLoss($service->fresh());
            $n++;
        }

        return back()->with('success', "Recalculated prices of {$n} auto-priced services.");
    }

    public function makeDefault(UserGroup $group): RedirectResponse
    {
        DB::transaction(function () use ($group) {
            UserGroup::where('is_default', true)->update(['is_default' => false]);
            $group->update(['is_default' => true, 'min_points_for_auto_upgrade' => null]);
        });

        ActivityLog::record('group.default_changed', null, ['group' => $group->name]);

        return back()->with('success', "New users now join \"{$group->name}\".");
    }

    public function destroy(UserGroup $group): RedirectResponse
    {
        if ($group->is_default) {
            return back()->with('error', 'Make another group the default before deleting this one.');
        }

        $default = UserGroup::where('is_default', true)->first();

        DB::transaction(function () use ($group, $default) {
            User::where('user_group_id', $group->id)->update(['user_group_id' => $default?->id]);
            $group->delete(); // its service prices are deleted by the foreign key
        });

        ActivityLog::record('group.deleted', null, ['group' => $group->name]);

        return back()->with('success', "Group deleted. Its users moved to \"{$default?->name}\".");
    }
}
