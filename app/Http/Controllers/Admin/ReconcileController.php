<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\ReconciliationIssue;
use App\Models\ReconciliationRun;
use App\Models\WalletTransaction;
use App\Services\ReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReconcileController extends Controller
{
    public function __construct(private ReconciliationService $service) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');
        $type = array_key_exists($request->query('type'), ReconciliationIssue::TYPES) ? $request->query('type') : null;
        $from = $request->query('from');
        $to = $request->query('to');

        return view('admin.reconcile.index', [
            'lastRun' => ReconciliationRun::with('trigger:id,username')->latest('id')->first(),
            'issues' => ReconciliationIssue::with('user:id,username')
                ->when($status === 'open', fn ($q) => $q->whereIn('status', ['open', 'proposed']))
                ->when($status !== 'open' && $status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($type, fn ($q) => $q->where('type', $type))
                ->orderByRaw("CASE severity WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")->latest('id')
                ->paginate(30)->withQueryString(),
            'counts' => ReconciliationIssue::whereIn('status', ['open', 'proposed'])->selectRaw('type, COUNT(*) AS n')->groupBy('type')->pluck('n', 'type'),
            'breakdown' => $this->service->breakdown($from, $to),
            'totals' => ['balances' => (float) \App\Models\User::sum('balance'), 'ledger' => (float) WalletTransaction::sum('amount')],
            'status' => $status, 'type' => $type, 'from' => $from, 'to' => $to,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $run = $this->service->run($request->user());

        return back()->with('success', "Checked {$run->users_checked} users and {$run->transactions_checked} transactions: {$run->issues_found} issue(s), {$run->new_issues} new.");
    }

    public function show(ReconciliationIssue $issue): View
    {
        $issue->load(['user', 'proposer:id,username', 'approver:id,username']);

        return view('admin.reconcile.show', [
            'issue' => $issue,
            'ledger' => $issue->user_id ? round((float) WalletTransaction::where('user_id', $issue->user_id)->sum('amount'), 2) : null,
            'recent' => $issue->user_id ? WalletTransaction::where('user_id', $issue->user_id)->latest('id')->limit(15)->get() : collect(),
        ]);
    }

    public function propose(Request $request, ReconciliationIssue $issue): RedirectResponse
    {
        $data = $request->validate([
            'correction' => ['required', Rule::in(array_keys(ReconciliationIssue::CORRECTIONS))],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:9999999999'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ]);

        return $this->attempt(fn () => $this->service->propose($issue, $request->user(), $data['correction'], isset($data['amount']) ? (float) $data['amount'] : null, $data['reason']),
            'Correction proposed. Another staff member must approve it.');
    }

    public function approve(Request $request, ReconciliationIssue $issue): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->approve($issue, $request->user()), 'Correction applied.');
    }

    public function reject(Request $request, ReconciliationIssue $issue): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->rejectProposal($issue, $request->user()), 'Proposal rejected. The issue is open again.');
    }

    public function ignore(Request $request, ReconciliationIssue $issue): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);

        return $this->attempt(fn () => $this->service->ignore($issue, $request->user(), $data['reason']), 'Marked as checked — no correction needed.');
    }

    public function export(Request $request): StreamedResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $breakdown = $this->service->breakdown($from, $to);

        return response()->streamDownload(function () use ($breakdown, $from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Wallet reconciliation report', 'Generated '.now()->format('Y-m-d H:i'), 'Period '.($from ?: 'start').' – '.($to ?: 'today')]);
            fputcsv($out, []);
            fputcsv($out, ['Type', 'Transactions', 'Total (Ks)']);
            foreach ($breakdown as $row) {
                fputcsv($out, [$row->type, $row->n, round((float) $row->total, 2)]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Issue', 'Type', 'Severity', 'Status', 'User', 'Expected', 'Actual', 'Message', 'Correction', 'Amount', 'Reason', 'Proposed by', 'Approved by', 'Found', 'Resolved']);
            foreach (ReconciliationIssue::with(['user:id,username', 'proposer:id,username', 'approver:id,username'])->latest('id')->limit(10000)->cursor() as $i) {
                fputcsv($out, \App\Support\Spreadsheet::safeRow([$i->id, $i->type, $i->severity, $i->status, $i->user?->username, $i->expected, $i->actual, $i->message,
                    $i->correction, $i->correction_amount, $i->reason, $i->proposer?->username, $i->approver?->username,
                    $i->created_at?->format('Y-m-d H:i'), $i->resolved_at?->format('Y-m-d H:i')]));
            }
            fclose($out);
        }, 'wallet-reconciliation-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function attempt(callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (InsufficientBalanceException) {
            return back()->with('error', 'The wallet does not have enough balance for this debit.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $ok);
    }
}
