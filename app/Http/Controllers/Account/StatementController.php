<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
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
