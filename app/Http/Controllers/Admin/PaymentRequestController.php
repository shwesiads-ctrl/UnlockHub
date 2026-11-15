<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentRequest;
use App\Services\PaymentApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentRequestController extends Controller
{
    public const REJECT_REASONS = [
        'Amount does not match',
        'Transaction not found',
        'Duplicate receipt',
        'Image unclear',
        'Wrong account / method',
    ];

    public function __construct(private PaymentApprovalService $approvals) {}

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected', 'all'], true)
            ? $request->query('status') : 'pending';

        $requests = PaymentRequest::query()
            ->with(['user:id,name,username', 'method:id,name', 'reviewer:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('q'), function ($q, $s) {
                $q->where(fn ($w) => $w->where('transaction_id', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")));
            })
            ->orderBy($status === 'pending' ? 'id' : 'reviewed_at', $status === 'pending' ? 'asc' : 'desc')
            ->paginate(30)->withQueryString();

        $counts = PaymentRequest::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        // Transaction IDs used more than once (same method) — flag in the list
        $dupes = PaymentRequest::selectRaw('payment_method_id, transaction_id')
            ->groupBy('payment_method_id', 'transaction_id')->havingRaw('count(*) > 1')
            ->get()->map(fn ($r) => $r->payment_method_id.'|'.$r->transaction_id)->flip();

        return view('admin.payments.index', compact('requests', 'status', 'counts', 'dupes'));
    }

    public function show(PaymentRequest $paymentRequest): View
    {
        $paymentRequest->load(['user.group', 'method', 'reviewer:id,name']);

        $others = PaymentRequest::where('payment_method_id', $paymentRequest->payment_method_id)
            ->where('transaction_id', $paymentRequest->transaction_id)
            ->whereKeyNot($paymentRequest->id)
            ->with('user:id,username')->get();

        $userStats = [
            'approved_total' => $paymentRequest->user->paymentRequests()->where('status', 'approved')->sum('credited_amount'),
            'approved_count' => $paymentRequest->user->paymentRequests()->where('status', 'approved')->count(),
            'rejected_count' => $paymentRequest->user->paymentRequests()->where('status', 'rejected')->count(),
        ];

        return view('admin.payments.show', [
            'pr' => $paymentRequest,
            'others' => $others,
            'userStats' => $userStats,
            'reasons' => self::REJECT_REASONS,
        ]);
    }

    public function approve(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $data = $request->validate([
            'credited_amount' => ['required', 'numeric', 'min:1', 'max:9999999999'],
            'admin_note' => ['nullable', 'string', 'max:500'],
            'paid_amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
            'rate' => ['nullable', 'numeric', 'gt:0', 'max:9999999'],
        ]);

        // Foreign-currency top-up: keep the amount and rate the admin confirmed,
        // so the record shows exactly how the Ks were worked out.
        if ($paymentRequest->isForeign() && $paymentRequest->status === PaymentRequest::PENDING) {
            $paymentRequest->forceFill(array_filter([
                'amount' => $data['paid_amount'] ?? null,
                'rate' => $data['rate'] ?? null,
            ]))->save();
        }

        try {
            $this->approvals->approve($paymentRequest, (float) $data['credited_amount'], $data['admin_note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->next($paymentRequest, 'Approved — '.money($data['credited_amount']).' credited to '.$paymentRequest->user->username.'.');
    }

    public function reject(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $data = $request->validate([
            'reject_reason' => ['required', 'string', 'max:255'],
            'custom_reason' => ['nullable', 'required_if:reject_reason,other', 'string', 'max:255'],
            'admin_note' => ['nullable', 'string', 'max:500'],
        ], ['custom_reason.required_if' => 'Write the reason the user will see.']);

        $reason = $data['reject_reason'] === 'other' ? $data['custom_reason'] : $data['reject_reason'];

        try {
            $this->approvals->reject($paymentRequest, $reason, $data['admin_note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return $this->next($paymentRequest, 'Rejected.');
    }

    public function receipt(PaymentRequest $paymentRequest): StreamedResponse
    {
        return Storage::disk(config('unlockhub.receipts.disk'))->response($paymentRequest->receipt_path);
    }

    /** After a decision, open the next pending request (oldest first). */
    private function next(PaymentRequest $current, string $message): RedirectResponse
    {
        $next = PaymentRequest::where('status', PaymentRequest::PENDING)->orderBy('id')->first();

        return $next
            ? redirect()->route('admin.payments.show', $next)->with('success', $message.' Next pending request opened.')
            : redirect()->route('admin.payments.index')->with('success', $message.' No more pending requests.');
    }
}
