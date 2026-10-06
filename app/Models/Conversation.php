<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConversationStatus;
use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'status',
        'client_id',
        'professional_id',
        'service_request_id',
        'order_id',
        'last_message_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'status' => ConversationStatus::class,
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class, 'professional_id');
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_message_id', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function unreadMessagesFor(User $user): HasMany
    {
        return $this->messages()
            ->where('sender_id', '!=', $user->getKey())
            ->where('id', '>', function ($query) use ($user): void {
                $query->selectRaw('COALESCE(last_read_message_id, 0)')
                    ->from('conversation_participants')
                    ->whereColumn('conversation_id', 'messages.conversation_id')
                    ->where('user_id', $user->getKey())
                    ->whereNull('left_at')
                    ->limit(1);
            });
    }
}
