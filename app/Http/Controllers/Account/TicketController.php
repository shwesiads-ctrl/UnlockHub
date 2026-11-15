<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Customer support tickets. A user only ever sees their own tickets and non-internal messages. */
class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request): View
    {
        return view('account.tickets.index', [
            'tickets' => $request->user()->tickets()->with('order:id,order_no')->latest('last_reply_at')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('account.tickets.create', [
            'orders' => $user->orders()->latest('id')->limit(50)->get(['id', 'order_no', 'service_name', 'imei', 'status']),
            'order' => $request->query('order') ? $user->orders()->where('order_no', $request->query('order'))->first() : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        \App\Services\Captcha::check($request, 'ticket');
        $user = $request->user();
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where('user_id', $user->id)],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'files' => ['array', 'max:'.TicketService::MAX_FILES],
            'files.*' => TicketService::FILE_RULES,
        ]);

        if ($user->tickets()->whereNotIn('status', ['resolved', 'closed'])->count() >= 10) {
            return back()->withInput()->with('error', 'You have 10 open tickets. Please wait for replies before opening more.');
        }

        $ticket = $this->tickets->open($user, $data, $request->file('files', []));

        return redirect()->route('account.tickets.show', $ticket)->with('success', "Ticket #{$ticket->ticket_no} opened. We'll reply here.");
    }

    public function show(Request $request, Ticket $ticket): View
    {
        $this->own($request, $ticket);
        $ticket->load(['order:id,order_no,service_name,status', 'publicMessages.attachments', 'publicMessages.author:id,name,username,avatar_path']);

        return view('account.tickets.show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->own($request, $ticket);
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'files' => ['array', 'max:'.TicketService::MAX_FILES],
            'files.*' => TicketService::FILE_RULES,
        ]);

        try {
            $this->tickets->customerReply($ticket, $request->user(), $data['message'], $request->file('files', []));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Reply sent.');
    }

    public function reopen(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->own($request, $ticket);

        try {
            $this->tickets->reopen($ticket);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ticket reopened.');
    }

    public function attachment(Request $request, Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        $this->own($request, $ticket);
        $attachment->loadMissing('message');

        // Must belong to this ticket and never to an internal note
        abort_unless($attachment->message && $attachment->message->ticket_id === $ticket->id && ! $attachment->message->is_internal, 404);
        abort_unless(Storage::disk(TicketAttachment::DISK)->exists($attachment->path), 404);

        return Storage::disk(TicketAttachment::DISK)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($attachment->isImage() ? 'inline' : 'attachment').'; filename="'.\App\Support\SafeName::header($attachment->original_name).'"',
        ]);
    }

    private function own(Request $request, Ticket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
    }
}
