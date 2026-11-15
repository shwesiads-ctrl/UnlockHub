<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\ResellerProfile;
use App\Models\Setting;
use App\Models\UserGroup;
use App\Models\WithdrawalRequest;
use App\Services\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class ResellerAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected', 'suspended'], true) ? $request->query('status') : null;
        $profiles = ResellerProfile::with('user:id,name,username,balance,status')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")->latest('id')
            ->paginate(30)->withQueryString();

        $ids = $profiles->pluck('user_id');
        $pointRate = max(1, (int) setting('points.convert_rate', 10));
        $stats = Order::whereIn('reseller_id', $ids)->where('status', Order::SUCCESS)->where('completed_at', '>=', now()->startOfMonth())
            ->selectRaw("reseller_id, COUNT(*) AS n, SUM(CASE WHEN price_currency = 'points' THEN reseller_price * COALESCE(price_rate, ?) ELSE reseller_price END) AS site, SUM(CASE WHEN price_currency = 'points' THEN (price - reseller_price) * COALESCE(price_rate, ?) ELSE price - reseller_price END) AS profit", [$pointRate, $pointRate])->groupBy('reseller_id')->get()->keyBy('reseller_id');
        $customers = \App\Models\User::whereIn('parent_id', $ids)->selectRaw('parent_id, COUNT(*) AS n')->groupBy('parent_id')->pluck('n', 'parent_id');

        return view('admin.resellers.index', [
            'profiles' => $profiles, 'stats' => $stats, 'customers' => $customers, 'status' => $status,
            'counts' => ResellerProfile::selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status'),
            'groups' => UserGroup::orderBy('sort')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, ResellerProfile $profile): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'suspended'])],
            'subdomain' => ['nullable', 'string', 'min:2', 'max:40', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::unique('reseller_profiles', 'subdomain')->ignore($profile->id)],
            'custom_domain' => ['nullable', 'string', 'max:120', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i', Rule::unique('reseller_profiles', 'custom_domain')->ignore($profile->id)],
            'admin_note' => ['nullable', 'string', 'max:500'],
            'group_id' => ['nullable', 'integer', 'exists:user_groups,id'],
            'is_verified' => ['required', 'boolean'],
        ]);

        $was = $profile->status;
        $verificationChanged = (bool) $profile->is_verified !== (bool) $data['is_verified'];
        $profile->update([
            'status' => $data['status'],
            'subdomain' => $data['subdomain'] ?? null,
            'custom_domain' => isset($data['custom_domain']) ? strtolower($data['custom_domain']) : null,
            'admin_note' => $data['admin_note'] ?? null,
            'approved_at' => $data['status'] === 'approved' ? ($profile->approved_at ?? now()) : $profile->approved_at,
            'is_verified' => (bool) $data['is_verified'],
            'verified_at' => $data['is_verified'] ? ($profile->verified_at ?? now()) : null,
            'verification_source' => $data['is_verified'] ? ($verificationChanged ? 'admin' : $profile->verification_source) : null,
        ]);
        if (! empty($data['group_id'])) {
            $profile->user->forceFill(['user_group_id' => $data['group_id']])->save(); // e.g. move to "Reseller" prices
        }
        if ($data['status'] === 'approved') {
            app(\App\Services\ResellerVerificationService::class)->verifyIfEligible((int) $profile->user_id);
        }
        ActivityLog::record('reseller.updated', $profile->user, ['from' => $was, 'to' => $data['status'], 'subdomain' => $data['subdomain'] ?? null, 'domain' => $data['custom_domain'] ?? null]);

        return back()->with('success', "{$profile->shop_name}: ".ucfirst($data['status']).'.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'default_markup' => ['required', 'numeric', 'min:0', 'max:1000'],
            'rounding' => ['required', 'in:1,10,50,100,500'],
            'base_domain' => ['nullable', 'string', 'max:120', 'regex:/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)+$/i'],
            'percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'basis' => ['required', 'in:profit,sale'],
            'months' => ['required', 'integer', 'min:0', 'max:120'],
            'withdraw_min' => ['required', 'numeric', 'min:0'],
            'withdraw_methods' => ['nullable', 'string', 'max:255'],
            'auto_verify_amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
        ]);

        foreach (['enabled', 'auto_approve', 'whitelabel', 'auto_verify_enabled'] as $k) {
            Setting::put('reseller.'.$k, $request->boolean('reseller_'.$k) ? '1' : '0');
        }
        Setting::put('reseller.auto_verify_amount', (string) $data['auto_verify_amount']);
        Setting::put('reseller.default_markup', (string) $data['default_markup']);
        Setting::put('reseller.rounding', (string) $data['rounding']);
        Setting::put('reseller.base_domain', $data['base_domain'] ? strtolower($data['base_domain']) : null);
        Setting::put('affiliate.enabled', $request->boolean('affiliate_enabled') ? '1' : '0');
        foreach (['percent', 'basis', 'months', 'withdraw_min', 'withdraw_methods'] as $k) {
            Setting::put('affiliate.'.$k, (string) ($data[$k] ?? ''));
        }
        ActivityLog::record('settings.resellers', null, $data);
        $verified = app(\App\Services\ResellerVerificationService::class)->verifyEligibleResellers();

        return back()->with('success', 'Reseller & commission settings saved.'.($verified ? " {$verified} reseller(s) reached the threshold and were verified." : ''));
    }

    // ------------------------------------------------------------ withdrawals

    public function withdrawals(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'paid', 'rejected'], true) ? $request->query('status') : 'pending';

        return view('admin.resellers.withdrawals', [
            'status' => $status,
            'items' => WithdrawalRequest::with(['user:id,username,name', 'reviewer:id,username'])->where('status', $status)->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function payWithdrawal(Request $request, WithdrawalRequest $withdrawal, AffiliateService $affiliate): RedirectResponse
    {
        $data = $request->validate(['reference' => ['nullable', 'string', 'max:120']]);
        try {
            $affiliate->markPaid($withdrawal, $request->user(), $data['reference'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Marked as paid.');
    }

    public function rejectWithdrawal(Request $request, WithdrawalRequest $withdrawal, AffiliateService $affiliate): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        try {
            $affiliate->reject($withdrawal, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rejected — the amount was returned to the wallet.');
    }
}
