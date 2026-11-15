<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    private function staff(Request $request): bool
    {
        return $request->routeIs('admin.support.*');
    }

    private function authorizeTicket(Request $request, SupportTicket $ticket): void
    {
        abort_unless(($this->staff($request) && $request->user()->isAdmin()) || $ticket->user_id === $request->user()->id, 404);
    }

    public function index(Request $request)
    {
        $staff = $this->staff($request);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'waiting', 'resolved', 'closed'])],
            'category' => ['nullable', Rule::in(array_keys(SupportTicket::CATEGORIES))],
        ]);
        $tickets = SupportTicket::with('user:id,name,username')
            ->when(! $staff, fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('support.index', compact('tickets', 'staff'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:2', 'max:5000'],
            'channel' => ['required', Rule::in(['ticket', 'chat'])],
            'category' => ['nullable', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'priority' => ['nullable', Rule::in(array_keys(SupportTicket::PRIORITIES))],
            'order_no' => ['nullable', 'string', Rule::exists('orders', 'order_no')->where('user_id', $request->user()->id)],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);
        $ticket = DB::transaction(function () use ($request, $data) {
            // Serialize chat creation so double clicks resume the same open chat.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ($data['channel'] === 'chat') {
                $existing = SupportTicket::where('user_id', $request->user()->id)->where('channel', 'chat')->whereIn('status', ['open', 'waiting'])->first();
                if ($existing) {
                    $this->addMessage($request, $existing, $data['message']);
                    $existing->touch();
                    return $existing;
                }
            }
            $ticket = SupportTicket::create([
                'user_id' => $request->user()->id, 'subject' => $data['subject'], 'channel' => $data['channel'],
                'category' => $data['category'] ?? 'general', 'priority' => $data['priority'] ?? 'normal',
                'order_id' => !empty($data['order_no']) ? Order::where('user_id', $request->user()->id)->where('order_no', $data['order_no'])->value('id') : null,
            ]);
            $this->addMessage($request, $ticket, $data['message']);
            return $ticket;
        });

        return redirect()->route('account.support.show', $ticket);
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $staff = $this->staff($request);
        $ticket->load('order');
        $messages = $ticket->messages()->orderByDesc('id')->paginate(50)->withQueryString();
        return view('support.show', compact('ticket', 'staff', 'messages'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'client_token' => ['nullable', 'uuid'],
            'after' => ['nullable', 'integer', 'min:0'],
        ]);
        DB::transaction(function () use ($request, $ticket, $data) {
            $locked = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if (!empty($data['client_token']) && $locked->messages()->where('user_id', $request->user()->id)->where('client_token', $data['client_token'])->exists()) {
                return;
            }
            abort_if(in_array($locked->status, ['closed', 'resolved']), 422, 'This conversation is closed.');
            $this->addMessage($request, $locked, $data['message']);
            $locked->status = $this->staff($request) ? 'waiting' : 'open';
            $locked->touch();
        });
        if ($request->expectsJson()) {
            return $this->messages($request, $ticket->refresh());
        }
        return redirect()->route(($this->staff($request) ? 'admin' : 'account').'.support.show', $ticket);
    }

    public function messages(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $prefix = $this->staff($request) ? 'admin' : 'account';
        return response()->json([
            'status' => $ticket->status,
            'messages' => $ticket->messages()->where('id', '>', $data['after'] ?? 0)->orderBy('id')->limit(100)
                ->get(['id', 'body', 'is_staff', 'created_at', 'attachment_path', 'attachment_name'])
                ->map(fn ($message) => [
                    'id' => $message->id, 'body' => $message->body, 'is_staff' => $message->is_staff, 'created_at' => $message->created_at,
                    'attachment_name' => $message->attachment_name,
                    'attachment_url' => $message->attachment_path ? route($prefix.'.support.attachment', [$ticket, $message]) : null,
                ]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request, SupportTicket $ticket)
    {
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'waiting', 'resolved', 'closed'])]]);
        $ticket->update($data);
        return back()->with('success', 'Conversation updated.');
    }

    private function addMessage(Request $request, SupportTicket $ticket, string $body): void
    {
        $path = $request->hasFile('attachment') ? $request->file('attachment')->store('support', 'local') : null;
        try {
            $ticket->messages()->create([
                'user_id' => $request->user()->id, 'body' => $body, 'is_staff' => $this->staff($request),
                'client_token' => $request->routeIs('*.support.reply') ? $request->input('client_token') : null,
                'attachment_path' => $path, 'attachment_name' => $path ? mb_substr(basename($request->file('attachment')->getClientOriginalName()), 0, 200) : null,
            ]);
        } catch (\Throwable $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function attachment(Request $request, SupportTicket $ticket, SupportMessage $message)
    {
        $this->authorizeTicket($request, $ticket);
        abort_unless($message->support_ticket_id === $ticket->id && $message->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);
        return Storage::disk('local')->download($message->attachment_path, $message->attachment_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
