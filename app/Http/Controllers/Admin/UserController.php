<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), ['latest', 'balance', 'points', 'last_login'], true) ? $request->query('sort') : 'latest';

        $users = User::query()
            ->with(['group:id,name,badge_color,badge_icon', 'resellerProfile:id,user_id,status'])
            ->withCount('orders')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('username', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('name', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")))
            ->when($request->integer('group'), fn ($q, $g) => $q->where('user_group_id', $g))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->when($sort === 'balance', fn ($q) => $q->orderByDesc('balance'))
            ->when($sort === 'points', fn ($q) => $q->orderByDesc('points'))
            ->when($sort === 'last_login', fn ($q) => $q->orderByDesc('last_login_at'))
            ->orderByDesc('id')
            ->paginate(30)->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'groups' => UserGroup::orderBy('sort')->get(),
            'sort' => $sort,
            'totals' => [
                'users' => User::count(),
                'balance' => (float) User::sum('balance'),
                'new_week' => User::where('created_at', '>=', now()->subWeek())->count(),
            ],
        ]);
    }

    public function show(User $user): View
    {
        $user->load(['group', 'referrer:id,username']);
        $pointRate = max(1, (int) setting('points.convert_rate', 10));

        $stats = [
            'spent' => (float) $user->orders()->where('status', Order::SUCCESS)->selectRaw("COALESCE(SUM(CASE WHEN price_currency = 'points' THEN price * COALESCE(price_rate, ?) ELSE price END), 0) AS total", [$pointRate])->value('total'),
            'profit' => (float) $user->orders()->where('status', Order::SUCCESS)->selectRaw("COALESCE(SUM(CASE WHEN price_currency = 'points' THEN COALESCE(reseller_price, price) * COALESCE(price_rate, ?) ELSE COALESCE(reseller_price, price) END - cost), 0) AS p", [$pointRate])->value('p'),
            'deposited' => (float) $user->paymentRequests()->where('status', PaymentRequest::APPROVED)->sum('credited_amount'),
            'orders' => $user->orders()->selectRaw('status, count(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'referrals' => $user->referrals()->count(),
        ];

        return view('admin.users.show', [
            'user' => $user,
            'stats' => $stats,
            'groups' => UserGroup::orderBy('sort')->get(),
            'orders' => $user->orders()->latest('id')->paginate(10, ['*'], 'orders_page')->withQueryString(),
            'payments' => $user->paymentRequests()->with('method:id,name')->latest('id')->paginate(8, ['*'], 'payments_page')->withQueryString(),
            'wallet' => $user->walletTransactions()->latest('id')->paginate(15, ['*'], 'wallet_page')->withQueryString(),
            'devices' => $user->trustedDevices()->where('expires_at', '>', now())->latest('last_used_at')->paginate(10, ['*'], 'devices_page')->withQueryString(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'user_group_id' => ['required', 'exists:user_groups,id'],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'role' => ['required', Rule::in(['user', 'admin'])],
        ]);

        if ($user->is($request->user()) && ($data['status'] !== 'active' || $data['role'] !== 'admin')) {
            return back()->with('error', "You can't suspend yourself or remove your own admin role.");
        }

        $before = $user->only(['email', 'user_group_id', 'status', 'role']);

        $user->forceFill([
            'name' => $data['name'],
            'email' => isset($data['email']) ? strtolower($data['email']) : null,
            'phone' => $data['phone'] ?? null,
            'user_group_id' => $data['user_group_id'],
            'status' => $data['status'],
            'role' => $data['role'],
        ])->save();

        ActivityLog::record('user.updated', $user, ['before' => $before, 'after' => $user->only(array_keys($before))]);

        return back()->with('success', 'User saved.');
    }

    public function wallet(Request $request, User $user, WalletService $wallet): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        try {
            $adj = app(\App\Services\WalletAdjustmentService::class)
                ->request($user, $data['direction'], (float) $data['amount'], $data['reason'], $request->user());
        } catch (InsufficientBalanceException) {
            return back()->withInput()->with('error', "Can't remove more than the balance (".money($user->balance).').');
        }

        if ($adj->status === 'pending') {
            return back()->with('success', money($data['amount']).' is waiting for a second staff member to approve (Wallet approvals).');
        }

        return back()->with('success', ($data['direction'] === 'credit' ? 'Added ' : 'Removed ').money($data['amount']).'. New balance '.money($user->fresh()->balance).'.');
    }

    public function statementPdf(Request $request, User $user, \App\Services\StatementService $statements): \Illuminate\Http\Response
    {
        [$from, $to] = \App\Services\StatementService::range($request->query('from'), $request->query('to'));
        $data = $statements->build($user, $from, $to);

        return response($statements->pdf($data), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$statements->filename($data).'"',
        ]);
    }

    public function statementEmail(Request $request, User $user, \App\Services\StatementService $statements): RedirectResponse
    {
        [$from, $to] = \App\Services\StatementService::range($request->input('from'), $request->input('to'));

        try {
            $sent = $statements->email($user, $from, $to);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not send the email: '.$e->getMessage());
        }

        if (! $sent) {
            return back()->with('error', "{$user->username} has no email address.");
        }

        ActivityLog::record('user.statement_emailed', $user, ['from' => $from->toDateString(), 'to' => $to->toDateString()]);

        return back()->with('success', "Statement ({$from->format('d M')} – {$to->format('d M Y')}) emailed to {$user->email}.");
    }

    public function resetTwoFactor(User $user): RedirectResponse
    {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        // Delete the records only — forgetAll() would also clear the admin's own cookie
        $user->trustedDevices()->delete();

        ActivityLog::record('user.2fa_reset', $user);

        return back()->with('success', "Google Authenticator turned off for {$user->username}. They'll get email codes until they set it up again.");
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        ActivityLog::record('user.password_reset_sent', $user);

        return back()->with($status === Password::RESET_LINK_SENT ? 'success' : 'error', __($status));
    }
}
