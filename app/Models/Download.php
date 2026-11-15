<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    protected $fillable = ['title', 'description', 'url', 'file_path', 'version', 'download_count', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
