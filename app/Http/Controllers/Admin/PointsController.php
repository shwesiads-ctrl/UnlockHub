<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PointTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PointsController extends Controller
{
    public function __construct(private PointService $points) {}

    public function index(Request $request): View
    {
        $type = $request->query('type');

        $transactions = PointTransaction::with('user:id,username')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($request->query('q'), fn ($q, $s) => $q->whereHas('user', fn ($u) => $u->where('username', 'like', "%{$s}%")))
            ->latest('id')
            ->paginate(40)->withQueryString();

        $totals = [
            'outstanding' => (int) User::sum('points'),
            'earned_month' => (int) PointTransaction::where('points', '>', 0)->whereIn('type', PointService::EARNING_TYPES)
                ->where('created_at', '>=', now()->startOfMonth())->sum('points'),
            'redeemed_month' => (int) abs(PointTransaction::where('type', 'redeem')->where('created_at', '>=', now()->startOfMonth())->sum('points')),
        ];

        return view('admin.points.index', [
            'rules' => PointService::rules(),
            'groups' => UserGroup::orderBy('sort')->get(),
            'transactions' => $transactions,
            'totals' => $totals,
            'type' => $type,
            'types' => \App\Http\Controllers\Account\PointsController::TYPES,
        ]);
    }

    public function updateRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'per_ks' => ['required', 'integer', 'min:0', 'max:100000000'],
            'redeem_rate' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'redeem_min' => ['required', 'integer', 'min:1', 'max:100000000'],
            'referral_bonus' => ['required', 'integer', 'min:0', 'max:100000000'],
            'thresholds' => ['array'],
            'thresholds.*' => ['nullable', 'integer', 'min:1'],
        ]);

        foreach (['per_ks', 'redeem_rate', 'redeem_min', 'referral_bonus'] as $key) {
            Setting::put('points.'.$key, (string) $data[$key]);
        }

        foreach (UserGroup::all() as $group) {
            $value = $data['thresholds'][$group->id] ?? null;
            $group->update(['min_points_for_auto_upgrade' => $group->is_default ? null : ($value ?: null)]);
        }

        ActivityLog::record('points.rules_updated', null, $data);

        return back()->with('success', 'Point rules saved.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'exists:users,username'],
            'direction' => ['required', 'in:add,subtract'],
            'points' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason' => ['required', 'string', 'max:200'],
        ], ['username.exists' => 'No user with that username.']);

        $user = User::where('username', $data['username'])->firstOrFail();

        try {
            $data['direction'] === 'add'
                ? $this->points->add($user, (int) $data['points'], 'admin', null, $data['reason'])
                : $this->points->subtract($user, (int) $data['points'], 'admin', null, $data['reason']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['points' => $e->getMessage().' ('.$user->username.' has '.number_format($user->points).').']);
        }

        ActivityLog::record('points.adjusted', $user, $data);

        if ($data['direction'] === 'add') {
            \App\Support\Notify::user($user, new \App\Notifications\PointsReceived((int) $data['points'], $data['reason']));
        }

        return back()->with('success', ($data['direction'] === 'add' ? 'Added ' : 'Removed ').number_format($data['points'])." points for {$user->username}. New balance: ".number_format($user->fresh()->points).'.');
    }
}
