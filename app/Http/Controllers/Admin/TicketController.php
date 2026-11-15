<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CannedReply;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\TicketService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(Request $request): View
    {
        $f = $request->only(['status', 'priority', 'category', 'assigned', 'q']);
        $status = $f['status'] ?? 'active';

        $tickets = Ticket::query()
            ->with(['user:id,username,name', 'assignee:id,username', 'order:id,order_no'])
            ->when($status === 'active', fn ($q) => $q->whereNotIn('status', ['resolved', 'closed']))
            ->when($status === 'needs_reply', fn ($q) => $q->where('awaiting_staff', true)->whereNotIn('status', ['resolved', 'closed']))
            ->when(array_key_exists($status, Ticket::STATUSES), fn ($q) => $q->where('status', $status))
            ->when(array_key_exists($f['priority'] ?? '', Ticket::PRIORITIES), fn ($q) => $q->where('priority', $f['priority']))
            ->when(array_key_exists($f['category'] ?? '', Ticket::CATEGORIES), fn ($q) => $q->where('category', $f['category']))
            ->when(($f['assigned'] ?? '') === 'me', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when(($f['assigned'] ?? '') === 'none', fn ($q) => $q->whereNull('assigned_to'))
            ->when(filled($f['q'] ?? null), function ($q) use ($f) {
                $s = trim($f['q']);
                $q->where(fn ($w) => $w->where('ticket_no', 'like', "%{$s}%")
                    ->orWhere('subject', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
                    ->orWhereHas('order', fn ($o) => $o->where('order_no', 'like', "%{$s}%")->orWhere('imei', 'like', "%{$s}%")));
            })
            ->orderByDesc('awaiting_staff')
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->latest('last_reply_at')
            ->paginate(30)->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'f' => $f + ['status' => $status],
            'counts' => [
                'needs_reply' => Ticket::where('awaiting_staff', true)->whereNotIn('status', ['resolved', 'closed'])->count(),
                'mine' => Ticket::where('assigned_to', $request->user()->id)->whereNotIn('status', ['resolved', 'closed'])->count(),
            ],
        ]);
    }

    public function show(Ticket $ticket): View
    {
        $ticket->load(['user', 'order.service:id,name,slug', 'assignee:id,username', 'messages.attachments', 'messages.author:id,name,username,avatar_path']);

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'staff' => $this->assignableStaff(),
            'canned' => CannedReply::orderBy('title')->get(),
            'recentOrders' => $ticket->user->orders()->latest('id')->limit(5)->get(['id', 'order_no', 'service_name', 'status', 'price', 'created_at']),
            'otherTickets' => $ticket->user->tickets()->whereKeyNot($ticket->id)->latest('id')->limit(5)->get(['id', 'ticket_no', 'subject', 'status']),
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'internal' => ['boolean'],
            'status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
            'files' => ['array', 'max:'.TicketService::MAX_FILES],
            'files.*' => TicketService::FILE_RULES,
        ]);

        $this->tickets->staffReply($ticket, $request->user(), $data['message'], $request->file('files', []), $request->boolean('internal'), $data['status'] ?? null);

        return back()->with('success', $request->boolean('internal') ? 'Internal note added (customer can\'t see it).' : 'Reply sent to the customer.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(Ticket::PRIORITIES))],
        ]);

        $this->tickets->update($ticket, $request->user(), array_filter($data));

        return back()->with('success', 'Ticket updated.');
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', Rule::in($this->assignableStaff()->pluck('id'))]]);

        $this->tickets->update($ticket, $request->user(), ['assigned_to' => $data['assigned_to'] ?? null]);

        return back()->with('success', 'Assignment saved.');
    }

    public function attachment(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('message');
        abort_unless($attachment->message?->ticket_id === $ticket->id, 404);
        abort_unless(Storage::disk(TicketAttachment::DISK)->exists($attachment->path), 404);

        return Storage::disk(TicketAttachment::DISK)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($attachment->isImage() ? 'inline' : 'attachment').'; filename="'.\App\Support\SafeName::header($attachment->original_name).'"',
        ]);
    }

    private function assignableStaff()
    {
        return User::where('role', User::ROLE_ADMIN)->orderBy('name')->get(['id', 'name', 'username', 'staff_role', 'role', 'status'])
            ->filter(fn ($u) => Permissions::allows($u, 'tickets.reply'))->values();
    }
}
