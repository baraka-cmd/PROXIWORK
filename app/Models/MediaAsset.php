<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'width', 'height', 'alt_text', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }
}
