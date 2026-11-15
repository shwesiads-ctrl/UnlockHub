<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Idempotency record: the same request key never places (or charges) twice. */
class OrderRequest extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'key', 'source', 'order_ids'];

    protected function casts(): array
    {
        return ['order_ids' => 'array'];
    }
}
