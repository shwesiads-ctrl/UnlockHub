<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stored on the private disk; only the ticket owner (non-internal messages) and support staff can download. */
class TicketAttachment extends Model
{
    public const DISK = 'local';

    protected $fillable = ['ticket_message_id', 'path', 'original_name', 'mime', 'size'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime, 'image/');
    }
}
