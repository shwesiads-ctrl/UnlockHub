<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Commission + reseller profit, and withdrawal requests. */
class EarningsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('resellerProfile');

        return view('account.earnings', [
            'user' => $user,
            'earnings' => $user->walletTransactions()->whereIn('type', AffiliateService::EARNING_TYPES)->latest('id')->paginate(20),
            'totals' => $user->walletTransactions()->whereIn('type', AffiliateService::EARNING_TYPES)
                ->selectRaw('type, SUM(amount) AS total')->groupBy('type')->pluck('total', 'type'),
            'withdrawable' => AffiliateService::withdrawable($user),
            'withdrawals' => \App\Models\WithdrawalRequest::where('user_id', $user->id)->latest('id')->limit(10)->get(),
            'referrals' => $user->referrals()->count(),
            'methods' => array_values(array_filter(array_map('trim', explode(',', (string) setting('affiliate.withdraw_methods', 'KBZPay,WavePay,AYA Pay,CB Pay,Bank transfer'))))),
        ]);
    }

    public function withdraw(Request $request, AffiliateService $affiliate): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'method' => ['required', 'string', 'max:40'],
            'account' => ['required', 'string', 'max:120'],
        ]);

        try {
            $affiliate->requestWithdrawal($request->user(), (float) $data['amount'], $data['method'], $data['account']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Withdrawal requested. It is paid to your account after review.');
    }
}
