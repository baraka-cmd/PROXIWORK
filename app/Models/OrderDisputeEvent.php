<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDisputeEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Les événements d’un litige sont immuables.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Les événements d’un litige ne peuvent pas être supprimés.');
        });
    }

    public $timestamps = false;
    protected $fillable = ['order_dispute_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'reason', 'metadata', 'created_at'];
    protected function casts(): array { return ['metadata' => 'array', 'created_at' => 'datetime']; }
    public function dispute(): BelongsTo { return $this->belongsTo(OrderDispute::class, 'order_dispute_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
