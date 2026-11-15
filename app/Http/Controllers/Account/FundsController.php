<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Add funds: transfer by mobile wallet, upload receipt, wait for admin approval. */
class FundsController extends Controller
{
    public function index(Request $request): View
    {
        if ($reseller = $request->user()->activeReseller()) {
            return view('account.funds-reseller', ['reseller' => $reseller->load('resellerProfile')]);
        }

        $user = $request->user();
        $countries = config('unlockhub.countries');

        // Switching the country tab remembers the choice on the account.
        $asked = strtoupper((string) $request->query('country'));
        if ($asked !== '' && isset($countries[$asked])) {
            session(['pay_country' => $asked]);
            if ($user->country !== $asked) {
                $user->forceFill(['country' => $asked])->save();
            }
        }

        $country = PaymentMethod::countryFor($user);
        $all = PaymentMethod::active()->get();
        $methods = $all->filter(fn ($m) => $m->country === null || $m->country === $country)->values();
        $selected = $methods->firstWhere('code', $request->query('method', old('method'))) ?? $methods->first();

        // Only offer tabs for countries that have at least one method turned on.
        $withMethods = $all->pluck('country')->filter()->unique();
        $tabs = collect($countries)->filter(fn ($label, $code) => $withMethods->contains($code) || $code === $country);

        return view('account.funds', [
            'methods' => $methods,
            'selected' => $selected,
            'country' => $country,
            'tabs' => $tabs->count() > 1 ? $tabs : collect(),
            'history' => $user->paymentRequests()->with('method:id,name')->latest()->paginate(10)->withQueryString(),
            'pendingCount' => $user->paymentRequests()->where('status', PaymentRequest::PENDING)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->activeReseller(), 403, 'Ask your reseller to add funds.');

        $request->validate([
            'method' => ['required', 'string'],
        ]);

        /** @var PaymentMethod $method */
        $method = PaymentMethod::active()->where('code', $request->input('method'))->firstOrFail();

        $receipt = config('unlockhub.receipts');
        $amountRules = ['required', 'numeric', 'gt:0', 'min:'.(float) $method->min_amount];
        if ($method->max_amount) {
            $amountRules[] = 'max:'.(float) $method->max_amount;
        }

        $data = $request->validate([
            'amount' => $amountRules,
            'transaction_id' => ['required', 'string', 'min:4', 'max:64', 'regex:/^[A-Za-z0-9\-\s]+$/'],
            'receipt' => ['required', 'file', 'image', 'mimes:'.implode(',', $receipt['mimes']), 'max:'.$receipt['max_kb']],
        ], [
            'amount.min' => 'Minimum top-up for '.$method->name.' is '.pay_amount($method->min_amount, $method->currency).'.',
            'amount.max' => 'Maximum top-up for '.$method->name.' is '.pay_amount($method->max_amount, $method->currency).'.',
            'transaction_id.regex' => 'Transaction ID can only contain letters, numbers, spaces and dashes.',
        ]);

        $txn = strtoupper(preg_replace('/\s+/', '', $data['transaction_id']));

        // Same receipt sent twice at once (double click / resend): only one request gets through
        $lock = \Illuminate\Support\Facades\Cache::lock('topup:'.$method->id.':'.$txn, 30);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['transaction_id' => 'This transaction ID is being submitted already. Please wait a moment.']);
        }

        try {
            return $this->createRequest($request, $user, $method, $data, $txn, $receipt);
        } finally {
            $lock->release();
        }
    }

    private function createRequest(Request $request, $user, PaymentMethod $method, array $data, string $txn, array $receipt): RedirectResponse
    {
        // Same transaction ID already submitted (pending or approved) → stop double credit
        $duplicate = PaymentRequest::where('payment_method_id', $method->id)
            ->where('transaction_id', $txn)
            ->where('status', '!=', PaymentRequest::REJECTED)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'transaction_id' => 'This transaction ID has already been submitted. Contact support if this is a mistake.',
            ]);
        }

        // Limit open requests so the queue can't be flooded
        if ($user->paymentRequests()->where('status', PaymentRequest::PENDING)->count() >= 5) {
            return back()->with('error', 'You already have 5 top-ups waiting for approval. Please wait for them to be reviewed.');
        }

        $path = $request->file('receipt')->store('receipts/'.now()->format('Y/m'), $receipt['disk']);

        $pr = $user->paymentRequests()->create([
            'payment_method_id' => $method->id,
            'amount' => $data['amount'],
            // Rate is copied now, so changing it later never changes this top-up.
            'currency' => $method->currency ?: 'MMK',
            'rate' => $method->currency === 'MMK' ? 1 : $method->rate,
            'transaction_id' => $txn,
            'receipt_path' => $path,
            'status' => PaymentRequest::PENDING,
        ]);

        \App\Support\Notify::admin('payment', '💰 <b>New top-up receipt</b>'
            ."\n".\App\Support\Notify::e($user->username).' · '.$method->name.' · '.pay_amount($data['amount'], $method->currency)
            .($method->isForeign() ? ' ≈ '.money($method->toKs((float) $data['amount'])) : '')
            ."\nTxn: ".\App\Support\Notify::e($txn)
            ."\n".route('admin.payments.show', $pr));

        return redirect()->route('account.funds.index')
            ->with('success', 'Receipt received. Your wallet will be credited after approval ('.setting('site.support_hours', '9 AM – 10 PM').').');
    }

    public function show(Request $request, PaymentRequest $paymentRequest): View
    {
        abort_unless($paymentRequest->user_id === $request->user()->id, 404);

        return view('account.payment', ['payment' => $paymentRequest->load('method')]);
    }

    /** Owner can view their own receipt image (private disk). */
    public function receipt(Request $request, PaymentRequest $paymentRequest): StreamedResponse
    {
        abort_unless($paymentRequest->user_id === $request->user()->id, 404);

        abort_unless($paymentRequest->receipt_path && Storage::disk(config('unlockhub.receipts.disk'))->exists($paymentRequest->receipt_path), 404);

        return Storage::disk(config('unlockhub.receipts.disk'))->response($paymentRequest->receipt_path);
    }

}
