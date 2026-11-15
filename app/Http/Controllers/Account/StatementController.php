<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\StatementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

/** Wallet statement: on screen (with date range), PDF download, PDF by email. */
class StatementController extends Controller
{
    /** Kept for other views that read the labels from here. */
    public const TYPES = StatementService::TYPES;

    public function __construct(private StatementService $statements) {}

    public function __invoke(Request $request): View
    {
        [$from, $to] = StatementService::range($request->query('from'), $request->query('to'));
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : null;

        $summary = $this->statements->build($request->user(), $from, $to);

        $transactions = $request->user()->walletTransactions()
            ->with('reference')
            ->whereBetween('created_at', [$from, $to])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('account.statement', [
            'transactions' => $transactions,
            'type' => $type,
            'types' => self::TYPES,
            'from' => $from,
            'to' => $to,
            'summary' => $summary,
        ]);
    }

    public function pdf(Request $request): Response
    {
        [$from, $to] = StatementService::range($request->query('from'), $request->query('to'));
        $type = array_key_exists($request->query('type'), self::TYPES) ? $request->query('type') : null;

        $data = $this->statements->build($request->user(), $from, $to, $type);

        return response($this->statements->pdf($data), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->statements->filename($data).'"',
        ]);
    }

    public function receipt(Request $request, WalletTransaction $transaction, bool $download = false): Response
    {
        abort_unless($transaction->user_id === $request->user()->id, 404);

        $rows = [
            'Receipt number' => 'WH-'.str_pad((string) $transaction->id, 8, '0', STR_PAD_LEFT),
            'Date' => $transaction->created_at->format('d M Y H:i'),
            'Account' => $request->user()->username,
            'Type' => self::TYPES[$transaction->type] ?? $transaction->type,
            'Amount' => ($transaction->amount > 0 ? '+' : '-').money(abs($transaction->amount)),
            'Balance before' => money((float) $transaction->balance_after - (float) $transaction->amount),
            'Balance after' => money($transaction->balance_after),
        ];

        $description = mb_str_split((string) $transaction->description, 54);
        $height = 610 + count($description) * 26;
        $svg = view('account.receipt-image', [
            'rows' => $rows,
            'description' => $description,
            'height' => $height,
            'site' => setting('site.name', config('app.name')),
        ])->render();

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="receipt-'.$transaction->id.'.svg"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }

    public function email(Request $request): RedirectResponse
    {
        $user = $request->user();
        [$from, $to] = StatementService::range($request->input('from'), $request->input('to'));
        $type = array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : null;

        if (! $user->email) {
            return back()->with('error', 'Add your email in My Profile first.');
        }

        try {
            $this->statements->email($user, $from, $to, $type);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Could not send the email right now. Please download the PDF instead.');
        }

        return back()->with('success', "Statement for {$from->format('d M')} – {$to->format('d M Y')} sent to {$user->email}.");
    }
}
