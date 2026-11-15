<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\UserGroup;
use App\Services\PointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class PointsController extends Controller
{
    public const TYPES = [
        'order' => 'Order',
        'referral' => 'Referral',
        'redeem' => 'Redeemed',
        'admin' => 'Admin',
        'reversal' => 'Reversal',
        'signup' => 'Welcome',
        'profile' => 'Profile',
        'topup' => 'Top-up bonus',
        'checkin' => 'Check-in',
    ];

    public function __construct(private PointService $points) {}

    public function index(Request $request): View
    {
        abort_unless(PointService::systemOn(), 404);

        $user = $request->user()->load('group');
        $rules = PointService::rules();

        $history = $user->pointTransactions()->latest('id')->paginate(20);

        $referrals = $user->referrals()
            ->select('id', 'username', 'created_at')
            ->withCount(['orders as success_orders' => fn ($q) => $q->where('status', Order::SUCCESS)])
            ->latest()->limit(50)->get();

        $lifetime = $this->points->lifetime($user);

        // Next group the user can reach by points
        $nextGroup = UserGroup::whereNotNull('min_points_for_auto_upgrade')
            ->where('min_points_for_auto_upgrade', '>', $lifetime)
            ->where('sort', '>', $user->group?->sort ?? 0)
            ->orderBy('min_points_for_auto_upgrade')->first();

        return view('account.points', [
            'user' => $user,
            'rules' => $rules,
            'history' => $history,
            'referrals' => $referrals,
            'lifetime' => $lifetime,
            'nextGroup' => $nextGroup,
            'types' => self::TYPES,
            'canCheckIn' => $this->points->canCheckInToday($user),
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        try {
            $r = $this->points->checkIn($request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = "+{$r['points']} points";
        $msg .= $r['streak'] > 1 ? " — {$r['streak']} days in a row!" : ' — see you tomorrow!';

        return back()->with('success', $msg);
    }

    public function redeem(Request $request): RedirectResponse
    {
        abort_unless(PointService::on('redeem'), 403, 'Redeeming points is turned off.');

        $user = $request->user();
        $rules = PointService::rules();

        $data = $request->validate([
            'points' => ['required', 'integer', 'min:'.max(1, $rules['redeem_min']), 'max:'.max(1, $user->points)],
        ], [
            'points.max' => 'You have '.number_format($user->points).' points.',
            'points.min' => 'Minimum '.number_format($rules['redeem_min']).' points per redeem.',
        ]);

        try {
            $amount = $this->points->redeem($user, (int) $data['points']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['points' => $e->getMessage()]);
        }

        return back()->with('success', number_format($data['points']).' points converted — '.money($amount).' added to your wallet.');
    }
}
