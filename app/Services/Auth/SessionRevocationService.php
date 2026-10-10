<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SessionRevocationService
{
    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->where('user_id', $user->getKey())->delete();
    }
}
