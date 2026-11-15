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
            'groups' => UserGroup::withCount(['users', 'prices'])->orderBy('sort')->get(),
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
        ]);

        $group->update($data);

        return back()->with('success', 'Group saved.');
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
