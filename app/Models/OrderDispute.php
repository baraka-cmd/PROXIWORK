<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderDisputePriority;
use App\Enums\OrderDisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderDispute extends Model
{
    protected $fillable = [
        'order_id', 'opened_by', 'client_id', 'professional_id', 'assigned_to',
        'reason_code', 'description', 'priority', 'status', 'resolution_code',
        'resolution_note', 'resolved_by', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => OrderDisputePriority::class,
            'status' => OrderDisputeStatus::class,
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function client(): BelongsTo { return $this->belongsTo(User::class, 'client_id'); }
    public function professional(): BelongsTo { return $this->belongsTo(ProfessionalProfile::class, 'professional_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function events(): HasMany { return $this->hasMany(OrderDisputeEvent::class)->oldest('id'); }
    public function messages(): HasMany { return $this->hasMany(OrderDisputeMessage::class)->oldest('id'); }
    public function attachments(): HasMany { return $this->hasMany(OrderDisputeAttachment::class)->latest('id'); }
}
