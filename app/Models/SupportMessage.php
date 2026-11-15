<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    protected $fillable = ['user_id', 'body', 'is_staff', 'attachment_path', 'attachment_name', 'client_token'];
    protected function casts(): array { return ['is_staff' => 'boolean']; }
}
