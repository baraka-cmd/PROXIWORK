<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDisputeMessage extends Model
{
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \\LogicException('Les événements d’un litige sont immuables.');
        });
        static::deleting(static function (): never {
            throw new \\LogicException('Les événements d’un litige ne peuvent pas être supprimés.');
        });
    }

    protected $fillable = ['order_dispute_id', 'sender_id', 'body', 'visibility'];
    protected function casts(): array { return []; }
    public function dispute(): BelongsTo { return $this->belongsTo(OrderDispute::class, 'order_dispute_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'sender_id'); }
    public function isInternal(): bool { return $this->visibility === 'internal'; }
}
