<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsPageVersion extends Model
{
    protected $fillable = ['cms_page_id', 'version', 'content', 'created_by'];

    protected function casts(): array
    {
        return ['content' => 'array', 'version' => 'integer'];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(CmsPage::class, 'cms_page_id');
    }
}
