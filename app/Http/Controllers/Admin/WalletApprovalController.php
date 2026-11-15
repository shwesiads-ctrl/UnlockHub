<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\WalletAdjustment;
use App\Services\WalletAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class WalletApprovalController extends Controller
{
    public function __construct(private WalletAdjustmentService $adjustments) {}

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : 'pending';

        return view('admin.wallet-approvals.index', [
            'status' => $status,
            'items' => WalletAdjustment::with(['user:id,username,name,balance', 'requester:id,username', 'reviewer:id,username'])
                ->where('status', $status)->latest('id')->paginate(30)->withQueryString(),
            'threshold' => WalletAdjustmentService::threshold(),
        ]);
    }

    public function approve(Request $request, WalletAdjustment $adjustment): RedirectResponse
    {
        try {
            $this->adjustments->approve($adjustment, $request->user(), $request->input('note'));
        } catch (InsufficientBalanceException) {
            return back()->with('error', 'The customer no longer has enough balance for this debit.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Approved and applied.');
    }

    public function reject(Request $request, WalletAdjustment $adjustment): RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        try {
            $this->adjustments->reject($adjustment, $request->user(), $request->input('note'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rejected. The wallet was not changed.');
    }
}
