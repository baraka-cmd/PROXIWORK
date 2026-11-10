<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'category', 'audience', 'priority', 'starts_at', 'ends_at', 'status', 'created_by', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'published_at' => 'datetime'];
    }

    public function scopeVisible(Builder $query, ?string $audience = null): Builder
    {
        return $query->where('status', 'published')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->when($audience, fn (Builder $q) => $q->whereIn('audience', ['all', $audience]))
            ->latest('published_at');
    }
}
