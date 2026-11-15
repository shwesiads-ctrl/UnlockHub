<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Service;
use App\Models\Setting;
use App\Support\Popup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** Home welcome popup + helpers for the service popup editor. */
class PopupController extends Controller
{
    public function edit(): View
    {
        return view('admin.popups.edit', [
            'popup' => Popup::home(),
            'services' => Service::whereNotNull('popup')->with('category:id,name')->orderBy('name')->get(['id', 'name', 'slug', 'status', 'popup', 'service_category_id']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['popup' => ['nullable', 'string', 'max:200000']]);

        $popup = Popup::fromJson($request->input('popup'));
        Setting::put('popup.home', $popup ? json_encode($popup) : '');
        ActivityLog::record('popup.home_updated', null, ['enabled' => $popup['enabled'] ?? false]);

        return back()->with('success', 'Welcome popup saved.');
    }

    /** Live preview HTML for the editor. */
    public function preview(Request $request): Response
    {
        $popup = Popup::sanitize(is_array($request->input('popup')) ? $request->input('popup') : []);

        return response(view('popups._preview', [
            'popup' => $popup,
            'title' => mb_substr((string) $request->input('title'), 0, 200),
            'delivery' => mb_substr((string) $request->input('delivery'), 0, 60),
        ])->render());
    }

    /** A service's popup as JSON (editor "copy from service"). */
    public function source(Service $service): JsonResponse
    {
        return response()->json($service->popup);
    }
}
