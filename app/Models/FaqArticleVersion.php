<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqArticleVersion extends Model
{
    protected $fillable = ['faq_article_id', 'version', 'content', 'created_by', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'content' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(FaqArticle::class, 'faq_article_id');
    }
}
