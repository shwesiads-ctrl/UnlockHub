<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\InsufficientBalanceException;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\BulkUploadService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

/** Bulk orders from a CSV / Excel file, with a preview before anything is ordered. */
class BulkOrderController extends Controller
{
    public function __construct(private BulkUploadService $bulk, private OrderService $orders) {}

    public function create(Service $service): View
    {
        $this->available($service);

        return view('account.bulk.upload', ['service' => $service->load('fields')]);
    }

    public function template(Service $service, string $format): Response
    {
        $this->available($service);
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);
        [$body, $name, $mime] = $this->bulk->template($service, $format);

        return response($body, 200, ['Content-Type' => $mime, 'Content-Disposition' => 'attachment; filename="'.$name.'"']);
    }

    public function upload(Request $request, Service $service): RedirectResponse
    {
        $this->available($service);
        $request->validate([
            'file' => ['required', 'file', 'max:'.BulkUploadService::maxKb(), 'extensions:csv,txt,xlsx'],
        ], ['file.extensions' => 'Upload a .csv or .xlsx file.']);

        try {
            $token = $this->bulk->upload($request->user(), $service, $request->file('file'));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('account.bulk.preview', [$service, $token]);
    }

    public function preview(Request $request, Service $service, string $token): View|RedirectResponse
    {
        try {
            $data = $this->bulk->get($request->user(), $token, $service);
        } catch (RuntimeException $e) {
            return redirect()->route('account.bulk.create', $service)->with('error', $e->getMessage());
        }

        $service->load('fields');

        return view('account.bulk.preview', [
            'service' => $service,
            'token' => $token,
            'data' => $data,
            'preview' => $this->bulk->preview($request->user(), $service, $data, $this->orders),
        ]);
    }

    public function mapping(Request $request, Service $service, string $token): RedirectResponse
    {
        try {
            $this->bulk->saveMapping($request->user(), $token, $service->load('fields'), (array) $request->input('map', []));
        } catch (RuntimeException $e) {
            return redirect()->route('account.bulk.create', $service)->with('error', $e->getMessage());
        }

        return redirect()->route('account.bulk.preview', [$service, $token]);
    }

    public function errors(Request $request, Service $service, string $token): Response|RedirectResponse
    {
        try {
            $data = $this->bulk->get($request->user(), $token, $service);
        } catch (RuntimeException $e) {
            return redirect()->route('account.bulk.create', $service)->with('error', $e->getMessage());
        }

        $csv = $this->bulk->errorFile($service, $data, $this->bulk->preview($request->user(), $service->load('fields'), $data, $this->orders));

        return response($csv, 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="bulk-errors.csv"']);
    }

    public function confirm(Request $request, Service $service, string $token): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Please confirm you checked the details.']);

        try {
            $placed = $this->bulk->confirm($request->user(), $service->load('fields'), $token, $request->boolean('include_duplicates'), $this->orders);
        } catch (InsufficientBalanceException $e) {
            return back()->with('error', $e->getMessage().' Add funds and try again.');
        } catch (RuntimeException $e) {
            // e.g. expired, price/availability changed, duplicates appeared since the preview
            return back()->with('error', $e->getMessage());
        }

        $first = $placed->first();

        return redirect()->route('account.orders.index', $first->batch_id ? ['batch' => $first->batch_id] : [])
            ->with('success', $placed->count().' order(s) placed. Total '.money($placed->sum('price')).'.');
    }

    private function available(Service $service): void
    {
        abort_unless($service->status === 'active', 404);
    }
}
