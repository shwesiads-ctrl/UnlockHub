<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketUpdated;
use App\Support\Notify;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TicketService
{
    public const MAX_FILES = 3;

    public const FILE_RULES = ['file', 'mimes:jpg,jpeg,png,webp,pdf,txt', 'max:5120'];

    /** @param  UploadedFile[]  $files */
    public function open(User $user, array $data, array $files = []): Ticket
    {
        $ticket = DB::transaction(function () use ($user, $data, $files) {
            $ticket = Ticket::create([
                'user_id' => $user->id,
                'order_id' => $data['order_id'] ?? null,
                'category' => $data['category'],
                'subject' => $data['subject'],
                'status' => 'open',
                'priority' => ($data['category'] ?? '') === 'payment' ? 'high' : 'normal',
                'awaiting_staff' => true,
                'last_reply_at' => now(),
            ]);
            $this->addMessage($ticket, $user, $data['message'], $files, staff: false, internal: false);

            return $ticket;
        });

        Notify::admin('ticket', '🎫 <b>New ticket</b> #'.$ticket->ticket_no
            ."\n".Notify::e($user->username).' · '.Ticket::CATEGORIES[$ticket->category]
            ."\n".Notify::e($ticket->subject)
            ."\n".route('admin.tickets.show', $ticket));

        return $ticket;
    }

    /** Customer reply. Reopens a resolved ticket inside the reopen window. */
    public function customerReply(Ticket $ticket, User $user, string $body, array $files = []): void
    {
        DB::transaction(function () use ($ticket, $user, $body, $files) {
            $t = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (! $t->customerCanReply()) {
                throw new RuntimeException('This ticket is closed. Please open a new ticket.');
            }

            $this->addMessage($t, $user, $body, $files, staff: false, internal: false);

            $t->forceFill([
                'status' => in_array($t->status, ['waiting_customer', 'resolved'], true) ? ($t->assigned_to ? 'in_progress' : 'open') : $t->status,
                'awaiting_staff' => true,
                'last_reply_at' => now(),
                'resolved_at' => null,
            ])->save();
        });

        Notify::admin('ticket', '💬 <b>Customer replied</b> #'.$ticket->ticket_no
            ."\n".Notify::e(Str::limit($body, 200))
            ."\n".route('admin.tickets.show', $ticket));
    }

    /** Reopen without writing (button), inside the window. */
    public function reopen(Ticket $ticket): void
    {
        if ($ticket->status !== 'resolved' || ! $ticket->customerCanReply()) {
            throw new RuntimeException('This ticket can no longer be reopened.');
        }
        $ticket->forceFill(['status' => 'open', 'awaiting_staff' => true, 'resolved_at' => null])->save();
    }

    /** Staff reply or internal note, with an optional status change. */
    public function staffReply(Ticket $ticket, User $staff, string $body, array $files, bool $internal, ?string $status): void
    {
        DB::transaction(function () use ($ticket, $staff, $body, $files, $internal, $status) {
            $t = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            $this->addMessage($t, $staff, $body, $files, staff: true, internal: $internal);

            if (! $internal) {
                $t->awaiting_staff = false;
                $t->last_reply_at = now();
                $t->assigned_to ??= $staff->id;
            }
            if ($status) {
                $this->setStatus($t, $status);
            } elseif (! $internal && in_array($t->status, ['open', 'in_progress'], true)) {
                $this->setStatus($t, 'waiting_customer');
            }
            $t->save();

            ActivityLog::record($internal ? 'ticket.note' : 'ticket.reply', $t, ['status' => $t->status]);
        });

        if (! $internal) {
            Notify::user($ticket->user()->first(), new TicketUpdated($ticket->fresh(), $this->replace($body, $ticket)));
        }
    }

    public function update(Ticket $ticket, User $staff, array $changes): void
    {
        DB::transaction(function () use ($ticket, $changes) {
            $t = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $before = $t->only(['status', 'priority', 'assigned_to']);

            if (isset($changes['status'])) {
                $this->setStatus($t, $changes['status']);
            }
            if (isset($changes['priority'])) {
                $t->priority = $changes['priority'];
            }
            if (array_key_exists('assigned_to', $changes)) {
                $t->assigned_to = $changes['assigned_to'] ?: null;
            }
            $t->save();

            ActivityLog::record('ticket.updated', $t, ['from' => $before, 'to' => $t->only(['status', 'priority', 'assigned_to'])]);
        });

        $fresh = $ticket->fresh();
        if (isset($changes['status']) && in_array($changes['status'], ['resolved', 'closed'], true) && $fresh->status === $changes['status']) {
            Notify::user($fresh->user()->first(), new TicketUpdated($fresh));
        }
    }

    /** {name} {ticket} {order} in saved replies. */
    public function replace(string $body, Ticket $ticket): string
    {
        $ticket->loadMissing(['user', 'order']);

        return strtr($body, [
            '{name}' => $ticket->user?->name ?? '',
            '{ticket}' => '#'.$ticket->ticket_no,
            '{order}' => $ticket->order ? '#'.$ticket->order->order_no : '',
        ]);
    }

    private function setStatus(Ticket $t, string $status): void
    {
        if (! array_key_exists($status, Ticket::STATUSES)) {
            return;
        }
        $t->status = $status;
        $t->resolved_at = $status === 'resolved' ? now() : ($status === 'closed' ? $t->resolved_at : null);
        $t->closed_at = $status === 'closed' ? now() : null;
        if (in_array($status, ['resolved', 'closed'], true)) {
            $t->awaiting_staff = false;
        }
    }

    /** @param  UploadedFile[]  $files */
    private function addMessage(Ticket $t, User $author, string $body, array $files, bool $staff, bool $internal): TicketMessage
    {
        $msg = $t->messages()->create([
            'user_id' => $author->id,
            'is_staff' => $staff,
            'is_internal' => $internal,
            'body' => $staff ? $this->replace($body, $t) : $body,
        ]);

        foreach (array_slice(array_filter($files), 0, self::MAX_FILES) as $file) {
            $msg->attachments()->create([
                'path' => $file->store('tickets/'.now()->format('Y/m'), TicketAttachment::DISK),
                'original_name' => Str::limit($file->getClientOriginalName(), 140, ''),
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => (int) $file->getSize(),
            ]);
        }

        return $msg;
    }
}
