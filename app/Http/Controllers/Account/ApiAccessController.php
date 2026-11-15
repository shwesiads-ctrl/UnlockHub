<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Account › API Access: key and IP whitelist for the DHRU-compatible reseller API. */
class ApiAccessController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account.api', [
            'user' => $user,
            'newKey' => session('api_key_plain'), // shown once right after generating
            'apiUrl' => url('/'),
            'endpoint' => route('api.dhru'),
            'recent' => $user->orders()->where('user_note', 'like', 'API%')->latest('id')->paginate(10)->withQueryString(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->email) {
            return back()->with('error', 'Add your email in My Profile before using the API.');
        }

        $key = strtoupper(Str::random(8).'-'.Str::random(8).'-'.Str::random(8).'-'.Str::random(8));

        $user->forceFill([
            'api_key_hash' => hash('sha256', $key),
            'api_key_hint' => substr($key, -4),
            'api_enabled' => true,
        ])->save();

        ActivityLog::record('api.key_generated', $user);

        return redirect()->route('account.api')
            ->with('api_key_plain', $key)
            ->with('success', 'New API key created. Copy it now — it will not be shown again. Any old key stopped working.');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(['api_ips' => ['nullable', 'string', 'max:1000']]);

        $ips = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) ($data['api_ips'] ?? '')))));
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP)) {
                throw ValidationException::withMessages(['api_ips' => "\"{$ip}\" is not a valid IP address."]);
            }
        }

        $user->forceFill([
            'api_ips' => $ips ? implode("\n", array_unique($ips)) : null,
            'api_enabled' => $request->boolean('api_enabled') && $user->api_key_hash,
        ])->save();

        return back()->with('success', 'API settings saved.');
    }

    public function revoke(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['api_key_hash' => null, 'api_key_hint' => null, 'api_enabled' => false])->save();
        ActivityLog::record('api.key_revoked', $request->user());

        return back()->with('success', 'API key deleted. Connections using it will stop.');
    }
}
