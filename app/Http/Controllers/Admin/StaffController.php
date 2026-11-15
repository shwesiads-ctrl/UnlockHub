<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Owner only: who is staff, which role, what each role may do. */
class StaffController extends Controller
{
    public function index(): View
    {
        return view('admin.staff.index', [
            'staff' => User::where('role', User::ROLE_ADMIN)->orderBy('name')->get(),
            'matrix' => Permissions::matrix(),
            'threshold' => (float) setting('wallet.approval_threshold', 0),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'staff_role' => ['required', Rule::in(array_keys(Permissions::ROLES))],
        ]);

        $user = User::where('username', $data['login'])->orWhere('email', $data['login'])->first();

        if (! $user) {
            return back()->withInput()->with('error', 'No account with that username or email. They must register first.');
        }
        if ($user->isAdmin()) {
            return back()->with('error', "{$user->username} is already staff.");
        }
        if (! $user->hasTwoFactor()) {
            return back()->withInput()->with('error', "{$user->username} must turn on Google Authenticator (My account › Security) before becoming staff.");
        }

        $user->forceFill(['role' => User::ROLE_ADMIN, 'staff_role' => $data['staff_role']])->save();
        ActivityLog::record('staff.added', $user, ['role' => $data['staff_role']]);

        return back()->with('success', "{$user->username} is now ".Permissions::ROLES[$data['staff_role']].'.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['staff_role' => ['required', Rule::in(array_keys(Permissions::ROLES))]]);

        abort_unless($user->isAdmin(), 404);
        if ($user->is($request->user())) {
            return back()->with('error', "You can't change your own role.");
        }
        if ($user->staffRole() === 'owner' && $data['staff_role'] !== 'owner' && $this->ownerCount() <= 1) {
            return back()->with('error', 'Keep at least one Owner.');
        }

        $old = $user->staffRole();
        $user->forceFill(['staff_role' => $data['staff_role']])->save();
        ActivityLog::record('staff.role_changed', $user, ['from' => $old, 'to' => $data['staff_role']]);

        return back()->with('success', "{$user->username}: ".Permissions::ROLES[$data['staff_role']].'.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);
        if ($user->is($request->user())) {
            return back()->with('error', "You can't remove yourself.");
        }
        if ($user->staffRole() === 'owner' && $this->ownerCount() <= 1) {
            return back()->with('error', 'Keep at least one Owner.');
        }

        $user->forceFill(['role' => 'user', 'staff_role' => null])->save();
        ActivityLog::record('staff.removed', $user);

        return back()->with('success', "{$user->username} is no longer staff (the customer account stays).");
    }

    public function permissions(Request $request): RedirectResponse
    {
        $input = (array) $request->input('perm', []);
        $matrix = [];
        foreach (array_keys(Permissions::ROLES) as $role) {
            if ($role === 'owner') {
                continue;
            }
            $matrix[$role] = array_values(array_diff(
                array_intersect(array_keys((array) ($input[$role] ?? [])), array_keys(Permissions::ALL)),
                Permissions::OWNER_ONLY,
            ));
        }

        $before = Permissions::matrix();
        Setting::put('staff.permissions', json_encode($matrix));

        $changes = [];
        foreach ($matrix as $role => $list) {
            $added = array_values(array_diff($list, $before[$role] ?? []));
            $removed = array_values(array_diff($before[$role] ?? [], $list));
            if ($added || $removed) {
                $changes[$role] = array_filter(['added' => $added, 'removed' => $removed]);
            }
        }
        ActivityLog::record('staff.permissions_changed', null, $changes);

        return back()->with('success', 'Permissions saved.');
    }

    public function threshold(Request $request): RedirectResponse
    {
        $data = $request->validate(['threshold' => ['nullable', 'numeric', 'min:0', 'max:9999999999']]);
        $old = setting('wallet.approval_threshold', 0);
        Setting::put('wallet.approval_threshold', (string) (float) ($data['threshold'] ?? 0));
        ActivityLog::record('staff.wallet_threshold', null, ['from' => (float) $old, 'to' => (float) ($data['threshold'] ?? 0)]);

        return back()->with('success', 'Approval threshold saved.');
    }

    private function ownerCount(): int
    {
        return User::where('role', User::ROLE_ADMIN)
            ->where(fn ($q) => $q->where('staff_role', 'owner')->orWhereNull('staff_role'))->count();
    }
}
