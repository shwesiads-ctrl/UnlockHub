<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Payment accounts per country (KBZPay, PromptPay, Alipay, BCEL One, MoMo…), each with its currency and rate. */
class PaymentMethodController extends Controller
{
    public function index(Request $request): View
    {
        $country = $request->query('country');

        return view('admin.payment-methods.index', [
            'country' => $country,
            'countries' => config('unlockhub.countries'),
            'methods' => PaymentMethod::query()
                ->when($country === 'all', fn ($q) => $q->whereNull('country'))
                ->when($country && $country !== 'all', fn ($q) => $q->where('country', $country))
                ->orderBy('sort')->withCount(['requests as pending_count' => fn ($q) => $q->where('status', 'pending')])->orderBy('id')->paginate(20)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'country' => ['nullable', Rule::in(array_keys(config('unlockhub.countries')))],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
        ]);

        $code = Str::slug($data['name'], '');
        $i = 1;
        while (PaymentMethod::where('code', $code)->exists()) {
            $code = Str::slug($data['name'], '').++$i;
        }

        PaymentMethod::create([
            'name' => $data['name'],
            'code' => $code,
            'country' => $data['country'] ?? null,
            'currency' => strtoupper($data['currency'] ?? 'MMK'),
            'rate' => 1,
            'is_active' => false,
            'sort' => (int) PaymentMethod::max('sort') + 1,
        ]);

        return back()->with('success', "{$data['name']} added. Fill in the account details, then turn it on.");
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'country' => ['nullable', Rule::in(array_keys(config('unlockhub.countries')))],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'gt:min_amount'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'qr' => ['nullable', 'image', 'max:2048'],
            'remove_qr' => ['boolean'],
        ]);

        $active = $request->boolean('is_active');
        if ($active && blank($data['account_number'])) {
            return back()->withErrors(["account_number_{$paymentMethod->id}" => 'Add the account number before turning this method on.']);
        }

        $currency = strtoupper($data['currency']);
        if ($currency === 'MMK') {
            $data['rate'] = 1;
        } elseif ($active && (float) $data['rate'] === 1.0) {
            // A foreign currency at 1:1 is almost certainly a rate that was never set.
            return back()->withErrors(["rate_{$paymentMethod->id}" => "Set how many Ks 1 {$currency} is worth before turning this method on."]);
        }

        if ($request->boolean('remove_qr') && $paymentMethod->qr_path) {
            Storage::disk('public')->delete($paymentMethod->qr_path);
            $paymentMethod->qr_path = null;
        }

        if ($request->hasFile('qr')) {
            if ($paymentMethod->qr_path) {
                Storage::disk('public')->delete($paymentMethod->qr_path);
            }
            $paymentMethod->qr_path = $request->file('qr')->store('payment-qr', 'public');
        }

        $paymentMethod->fill([
            'name' => $data['name'],
            'country' => $data['country'] ?? null,
            'currency' => $currency,
            'rate' => $data['rate'],
            'account_name' => $data['account_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'min_amount' => $data['min_amount'],
            'max_amount' => $data['max_amount'] ?? null,
            'sort' => $data['sort'] ?? 0,
            'is_active' => $active,
        ])->save();

        ActivityLog::record('payment_method.updated', null, ['method' => $paymentMethod->code, 'active' => $active, 'country' => $paymentMethod->country, 'currency' => $currency, 'rate' => (float) $data['rate']]);

        return back()->with('success', "{$paymentMethod->name} saved.");
    }
}
