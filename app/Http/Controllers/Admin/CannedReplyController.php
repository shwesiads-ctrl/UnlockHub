<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CannedReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Saved replies for tickets. Placeholders: {name} {ticket} {order}. */
class CannedReplyController extends Controller
{
    public function index(): View
    {
        return view('admin.tickets.canned', ['replies' => CannedReply::orderBy('title')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        CannedReply::create($request->validate(['title' => ['required', 'string', 'max:100'], 'body' => ['required', 'string', 'max:5000']]));

        return back()->with('success', 'Saved reply added.');
    }

    public function update(Request $request, CannedReply $canned): RedirectResponse
    {
        $canned->update($request->validate(['title' => ['required', 'string', 'max:100'], 'body' => ['required', 'string', 'max:5000']]));

        return back()->with('success', 'Saved reply updated.');
    }

    public function destroy(CannedReply $canned): RedirectResponse
    {
        $canned->delete();

        return back()->with('success', 'Saved reply deleted.');
    }
}
