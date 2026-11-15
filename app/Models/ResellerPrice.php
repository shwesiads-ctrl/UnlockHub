<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResellerPrice extends Model
{
    protected $fillable = ['reseller_id', 'service_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }
}
