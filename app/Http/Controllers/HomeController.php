<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Models\Service;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $hot = Service::visible()->where('is_hot', true)
            ->with(['category', 'prices'])
            ->orderBy('sort')->limit(8)->get();

        return view('home', [
            'hot' => $hot,
            'paymentMethods' => PaymentMethod::active()->get(),
        ]);
    }
}
