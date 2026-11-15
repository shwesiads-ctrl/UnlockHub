<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    public const CATEGORIES = ['order' => 'Order', 'payment' => 'Payment', 'account' => 'Account', 'other' => 'Other'];

    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'waiting_customer' => 'Waiting for Customer',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    protected $fillable = ['user_id', 'order_id', 'category', 'subject', 'status', 'priority', 'assigned_to', 'awaiting_staff', 'last_reply_at'];

    protected function casts(): array
    {
        return [
            'awaiting_staff' => 'boolean',
            'last_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $t) {
            do {
                $no = 'T'.strtoupper(Str::random(7));
            } while (static::where('ticket_no', $no)->exists());
            $t->ticket_no ??= $no;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ticket_no';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public static function reopenDays(): int
    {
        return max(0, (int) setting('tickets.reopen_days', 7));
    }

    /** Customer can still reopen / reply. */
    public function customerCanReply(): bool
    {
        if ($this->status === 'closed') {
            return false;
        }

        return $this->status !== 'resolved' || ($this->resolved_at && $this->resolved_at->gt(now()->subDays(self::reopenDays())));
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            'open' => 'badge-pending',
            'in_progress' => 'badge-info',
            'waiting_customer' => 'badge-hot',
            'resolved' => 'badge-success',
            default => 'bg-surface-2 text-muted',
        };
    }
}
