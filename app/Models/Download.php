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

    /** New uploads live on the private "local" disk; older ones may still be on "public". */
    public static function diskFor(string $path): string
    {
        return \Illuminate\Support\Facades\Storage::disk('local')->exists($path) ? 'local' : 'public';
    }
}
