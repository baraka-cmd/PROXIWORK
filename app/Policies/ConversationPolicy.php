<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return DB::table('conversation_participants')
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->exists();
    }

    public function send(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function read(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
