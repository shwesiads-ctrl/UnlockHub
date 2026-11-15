<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    public const PENDING = 'pending';
    public const IN_PROCESS = 'in_process';
    public const SUCCESS = 'success';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::PENDING => 'Pending',
        self::IN_PROCESS => 'In Process',
        self::SUCCESS => 'Success',
        self::REJECTED => 'Rejected',
        self::CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'order_no', 'batch_id', 'user_id', 'service_id', 'service_name', 'input', 'imei',
        'user_note', 'price', 'cost', 'points', 'status', 'processing_mode', 'supplier_id',
        'supplier_order_id', 'api_attempts', 'api_failed', 'api_error', 'api_next_check_at',
        'result', 'admin_note', 'refunded', 'points_awarded', 'processed_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'points' => 'integer',
            'api_failed' => 'boolean',
            'refunded' => 'boolean',
            'points_awarded' => 'boolean',
            'api_next_check_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->order_no ??= static::generateOrderNo();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'order_no';
    }

    public static function generateOrderNo(): string
    {
        do {
            $no = 'UH'.now()->format('ymd').random_int(10000, 99999);
        } while (static::where('order_no', $no)->exists());

        return $no;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function walletTransactions(): MorphMany
    {
        return $this->morphMany(WalletTransaction::class, 'reference');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::IN_PROCESS], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    /** Orders waiting for a human: manual mode (incl. API fallbacks) that are still open. */
    public function scopeManualQueue(Builder $q): Builder
    {
        return $q->where('processing_mode', 'manual')
            ->whereIn('status', [self::PENDING, self::IN_PROCESS]);
    }
}
