<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDisputeAttachment extends Model
{
    protected $fillable = ['order_dispute_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size'];
    public function dispute(): BelongsTo { return $this->belongsTo(OrderDispute::class, 'order_dispute_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
