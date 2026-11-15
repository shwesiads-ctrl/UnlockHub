<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Visitors can switch which country's payment methods they see.
        $asked = strtoupper((string) $request->query('country'));
        if ($asked !== '' && isset(config('unlockhub.countries')[$asked])) {
            session(['pay_country' => $asked]);
            $request->user()?->forceFill(['country' => $asked])->save();
        }
        $country = PaymentMethod::countryFor($request->user());
        $active = PaymentMethod::active()->get();

        $hot = Service::visible()->where('is_hot', true)
            ->with(['category', 'prices' => fn ($p) => $p->where('user_group_id', auth()->user()?->user_group_id ?? 0)])
            ->orderBy('sort')->limit(8)->get();

        return view('home', [
            'hot' => $hot,
            'payCountry' => $country,
            'paymentMethods' => $active->filter(fn ($m) => $m->country === null || $m->country === $country)->values(),
            // Other countries that have methods turned on, for the switcher.
            'payCountries' => collect(config('unlockhub.countries'))
                ->filter(fn ($label, $code) => $active->contains('country', $code)),
        ]);
    }
}
