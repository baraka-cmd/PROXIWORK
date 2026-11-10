<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicPageVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['public_page_id', 'version', 'snapshot', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'created_at' => 'datetime'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(PublicPage::class, 'public_page_id');
    }
}