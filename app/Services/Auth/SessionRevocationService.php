<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SessionRevocationService
{
    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();

        $user->forceFill([
            'session_version' => (int) $user->session_version + 1,
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');

        if (Schema::hasTable($table) === false) {
            return;
        }

        DB::table($table)->where('user_id', $user->getKey())->delete();
    }
}
