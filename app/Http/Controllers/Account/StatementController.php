<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Wallet statement: every credit and debit with running balance. */
class StatementController extends Controller
{
    public const TYPES = [
        'deposit' => 'Top-up',
        'order' => 'Order',
        'refund' => 'Refund',
        'points_redeem' => 'Points redeemed',
        'admin_credit' => 'Admin credit',
        'admin_debit' => 'Admin debit',
    ];

    public function __invoke(Request $request): View
    {
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : null;

        $transactions = $request->user()->walletTransactions()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('account.statement', [
            'transactions' => $transactions,
            'type' => $type,
            'types' => self::TYPES,
        ]);
    }
}
