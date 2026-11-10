<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPage extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'status', 'seo_title', 'meta_description', 'og_image_path', 'canonical_url', 'is_indexable', 'created_by', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['is_indexable' => 'boolean', 'published_at' => 'datetime'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(CmsPageVersion::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
