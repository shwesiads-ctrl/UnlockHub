<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const CATEGORIES = ['general' => 'General enquiry', 'order' => 'Order issue', 'payment' => 'Payment / Wallet', 'account' => 'Account / Verification', 'technical' => 'Technical issue'];
    public const PRIORITIES = ['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    protected $fillable = ['user_id', 'subject', 'channel', 'status', 'category', 'priority', 'order_id'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function messages(): HasMany { return $this->hasMany(SupportMessage::class); }
}
