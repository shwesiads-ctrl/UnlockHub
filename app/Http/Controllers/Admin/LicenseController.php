<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\LicenseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin › License: enter the serial key, see the domain lock status. Owner only. */
class LicenseController extends Controller
{
    public function show(Request $request): View
    {
        return view('admin.license', [
            'status' => LicenseService::status($request),
            'serial' => LicenseService::serial(),
            'host' => LicenseService::host(),
            'enabled' => LicenseService::enabled(),
            'server' => config('license.server'),
            'checkedAt' => setting('license.checked_at'),
        ]);
    }

    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate(['serial' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/']]);
        $r = LicenseService::activate($data['serial']);
        ActivityLog::record('license.activate', null, ['ok' => $r['ok'], 'domain' => LicenseService::host()]);

        return $r['ok']
            ? redirect()->route('admin.license')->with('success', 'License activated for '.LicenseService::host().'.')
            : back()->withInput()->with('error', $r['message'] ?: 'Activation failed.');
    }

    public function check(): RedirectResponse
    {
        $r = LicenseService::refresh();

        return back()->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'License checked — all good.' : ($r['message'] ?: 'Check failed.'));
    }

    public function deactivate(Request $request): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:'.LicenseService::host()]], ['confirm.in' => 'Type the domain exactly to confirm.']);
        $r = LicenseService::deactivate();
        ActivityLog::record('license.deactivate', null, ['ok' => $r['ok'], 'domain' => LicenseService::host()]);

        return back()->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'The key was released. Activate it on the new domain.' : ($r['message'] ?: 'Could not release the key.'));
    }
}
