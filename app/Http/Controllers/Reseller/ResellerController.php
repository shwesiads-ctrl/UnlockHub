<?php

namespace App\Http\Controllers\Reseller;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ResellerPrice;
use App\Models\ResellerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\AffiliateService;
use App\Services\ResellerService;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

/** Reseller panel: customers, prices, orders, branding. */
class ResellerController extends Controller
{
    public function __construct(private ResellerService $resellers) {}

    // ------------------------------------------------------------ apply (any customer)

    public function apply(Request $request): View|RedirectResponse
    {
        $user = $request->user()->load('resellerProfile');
        if ($user->isReseller()) {
            return redirect()->route('reseller.dashboard');
        }

        return view('reseller.apply', ['user' => $user, 'profile' => $user->resellerProfile]);
    }

    public function submitApplication(Request $request): RedirectResponse
    {
        $user = $request->user()->load('resellerProfile');
        abort_unless(ResellerService::enabled(), 404);
        if ($user->parent_id) {
            return back()->with('error', 'Accounts that belong to a reseller cannot become resellers.');
        }
        if ($user->resellerProfile && in_array($user->resellerProfile->status, ['approved', 'suspended'], true)) {
            return back();
        }

        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:80'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_telegram' => ['nullable', 'string', 'max:60'],
            'application_note' => ['nullable', 'string', 'max:500'],
        ]);

        $auto = setting('reseller.auto_approve', '0') === '1';
        ResellerProfile::updateOrCreate(['user_id' => $user->id], $data + [
            'status' => $auto ? 'approved' : 'pending',
            'approved_at' => $auto ? now() : null,
            'markup_value' => (float) setting('reseller.default_markup', 10),
        ]);

        if (! $auto) {
            Notify::admin('payment', "🤝 <b>Reseller application</b>\n".Notify::e($user->username).' · '.Notify::e($data['shop_name'])."\n".route('admin.resellers.index'));
        }

        return $auto
            ? redirect()->route('reseller.dashboard')->with('success', 'Welcome! Your reseller panel is ready.')
            : back()->with('success', 'Application sent. We will review it soon.');
    }

    // ------------------------------------------------------------ panel

    public function dashboard(Request $request): View
    {
        $me = $request->user()->load('resellerProfile');
        $month = now()->startOfMonth();
        $orders = Order::where('reseller_id', $me->id);
        $done = (clone $orders)->where('status', Order::SUCCESS)->where('completed_at', '>=', $month);

        return view('reseller.dashboard', [
            'me' => $me,
            'customers' => $me->subUsers()->count(),
            'customerBalance' => (float) $me->subUsers()->sum('balance'),
            'month' => [
                'orders' => (clone $orders)->where('created_at', '>=', $month)->count(),
                'sales' => (float) (clone $done)->sum('price'),
                'profit' => (float) (clone $done)->selectRaw('COALESCE(SUM(price - reseller_price), 0) AS p')->value('p'),
            ],
            'open' => (clone $orders)->whereIn('status', [Order::PENDING, Order::IN_PROCESS])->count(),
            'recent' => (clone $orders)->with('user:id,username')->latest('id')->limit(10)->get(),
            'withdrawable' => AffiliateService::withdrawable($me),
        ]);
    }

    public function customers(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('reseller.customers', [
            'customers' => $request->user()->subUsers()
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('username', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
                ->withCount(['orders as open_orders' => fn ($o) => $o->whereIn('status', [Order::PENDING, Order::IN_PROCESS])])
                ->latest('id')->paginate(25)->withQueryString(),
            'q' => $q,
        ]);
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'alpha_dash', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', Password::min(8)],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $customer = $this->resellers->createCustomer($request->user(), $data);

        if (($data['opening_balance'] ?? 0) > 0) {
            try {
                $this->resellers->transfer($request->user(), $customer, (float) $data['opening_balance'], 'Opening balance');
            } catch (InsufficientBalanceException) {
                return redirect()->route('reseller.customers.show', $customer)->with('error', "Customer created, but your wallet doesn't have enough for the opening balance.");
            }
        }

        return redirect()->route('reseller.customers.show', $customer)->with('success', "Customer {$customer->username} created. They log in with this username and the password you set.");
    }

    public function showCustomer(Request $request, User $customer): View
    {
        $this->own($request, $customer);

        return view('reseller.customer', [
            'customer' => $customer,
            'orders' => $customer->orders()->latest('id')->paginate(20),
            'transactions' => $customer->walletTransactions()->latest('id')->limit(15)->get(),
        ]);
    }

    public function updateCustomer(Request $request, User $customer): RedirectResponse
    {
        $this->own($request, $customer);
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => ['nullable', Password::min(8)],
        ]);
        $customer->forceFill(['status' => $data['status']]);
        if (filled($data['password'] ?? null)) {
            $customer->password = $data['password'];
        }
        $customer->save();

        return back()->with('success', 'Customer updated.');
    }

    public function transfer(Request $request, User $customer): RedirectResponse
    {
        $this->own($request, $customer);
        $data = $request->validate([
            'direction' => ['required', 'in:give,take'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'note' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $this->resellers->transfer($request->user()->load('resellerProfile'), $customer, $data['direction'] === 'give' ? (float) $data['amount'] : -(float) $data['amount'], $data['note'] ?? null);
        } catch (InsufficientBalanceException) {
            return back()->with('error', $data['direction'] === 'give' ? 'Not enough balance in your wallet.' : 'The customer does not have that much.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', ($data['direction'] === 'give' ? 'Added ' : 'Took back ').money($data['amount']).'.');
    }

    public function prices(Request $request): View
    {
        $me = $request->user()->load('resellerProfile');
        $q = trim((string) $request->query('q'));
        $services = Service::visible()->with('category:id,name')
            ->when($q !== '', fn ($w) => $w->where('name', 'like', "%{$q}%"))
            ->orderBy('service_category_id')->orderBy('name')->paginate(40)->withQueryString();
        $own = ResellerPrice::where('reseller_id', $me->id)->whereIn('service_id', $services->pluck('id'))->pluck('price', 'service_id');

        return view('reseller.prices', compact('me', 'services', 'own', 'q'));
    }

    public function updatePrices(Request $request): RedirectResponse
    {
        $me = $request->user()->load('resellerProfile');
        $data = $request->validate([
            'markup_type' => ['required', 'in:percent,fixed'],
            'markup_value' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'prices' => ['array'],
            'prices.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        $me->resellerProfile->update(['markup_type' => $data['markup_type'], 'markup_value' => $data['markup_value']]);

        $below = 0;
        foreach ($data['prices'] ?? [] as $serviceId => $price) {
            $service = Service::find((int) $serviceId);
            if (! $service) {
                continue;
            }
            if ($price === null || $price === '') {
                ResellerPrice::where('reseller_id', $me->id)->where('service_id', $service->id)->delete();

                continue;
            }
            $cost = ResellerService::costFor($service, $me);
            if ((float) $price < $cost) {
                $below++;
            }
            ResellerPrice::updateOrCreate(['reseller_id' => $me->id, 'service_id' => $service->id], ['price' => max((float) $price, $cost)]);
        }

        return back()->with('success', 'Prices saved.'.($below ? " {$below} price(s) were below your cost and were raised to your cost." : ''));
    }

    public function orders(Request $request): View
    {
        $status = array_key_exists($request->query('status'), Order::STATUS_LABELS) ? $request->query('status') : null;

        return view('reseller.orders', [
            'orders' => Order::where('reseller_id', $request->user()->id)->with('user:id,username')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('order_no', 'like', "%{$s}%")->orWhere('imei', 'like', "%{$s}%")))
                ->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function branding(Request $request): View
    {
        $me = $request->user()->load('resellerProfile');

        return view('reseller.branding', ['me' => $me, 'profile' => $me->resellerProfile]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $profile = $request->user()->resellerProfile;
        $reserved = ['www', 'admin', 'api', 'mail', 'cpanel', 'webmail', 'webdisk', 'ftp', 'app', 'static', 'cdn', 'ns1', 'ns2', 'smtp', 'pop', 'imap'];
        $data = $request->validate([
            'shop_name' => ['required', 'string', 'max:80'],
            'subdomain' => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', Rule::notIn($reserved), Rule::unique('reseller_profiles', 'subdomain')->ignore($profile->id)],
            'brand_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_telegram' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'about' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ], ['subdomain.regex' => 'Use lowercase letters, numbers and dashes.', 'subdomain.not_in' => 'That name is reserved.']);

        if (setting('reseller.whitelabel', '1') !== '1') {
            unset($data['subdomain']);
        }
        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('resellers', 'public');
        }
        unset($data['logo']);
        $data['allow_signup'] = $request->boolean('allow_signup');
        $profile->update($data);

        return back()->with('success', 'Branding saved.');
    }

    private function own(Request $request, User $customer): void
    {
        abort_unless($customer->parent_id === $request->user()->id, 404);
    }
}
