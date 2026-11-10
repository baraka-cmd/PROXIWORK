<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\SecuritySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class SecuritySessionService
{
    public function touch(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $identifier = 'sanctum:'.$token->getKey();
            $guard = 'sanctum';
            $tokenId = (int) $token->getKey();
            $expiresAt = $token->expires_at;
            $deviceName = (string) ($token->name ?: 'Appareil mobile');
        } elseif ($request->hasSession() && auth('web')->check()) {
            $identifier = 'web:'.$request->session()->getId();
            $guard = 'web';
            $tokenId = null;
            $expiresAt = now()->addMinutes((int) config('session.lifetime', 120));
            $deviceName = 'Navigateur web';
        } else {
            return;
        }

        $hash = hash_hmac('sha256', $identifier, (string) config('app.key'));
        $existing = SecuritySession::query()
            ->where('guard', $guard)
            ->where('identifier_hash', $hash)
            ->first();

        if ($existing?->revoked_at !== null) {
            throw ValidationException::withMessages(['session' => 'Cette session a été révoquée. Reconnectez-vous.']);
        }

        if ($existing === null) {
            $session = SecuritySession::query()->create([
                'user_id' => $user->getKey(),
                'guard' => $guard,
                'identifier_hash' => $hash,
                'identifier_ciphertext' => $guard === 'web' ? $request->session()->getId() : null,
                'token_id' => $tokenId,
                'device_name' => $deviceName,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'last_seen_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            app(AuditLogService::class)->recordEvent(
                'security.session.started',
                $session,
                $user,
                ['guard' => $guard, 'device_name' => $deviceName],
                $request,
                category: 'security',
            );

            return;
        }

        if ($existing->last_seen_at === null || $existing->last_seen_at->lt(now()->subMinutes(5))) {
            $existing->forceFill([
                'last_seen_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'expires_at' => $expiresAt,
            ])->save();
        }
    }

    public function revoke(SecuritySession $session, User $actor, string $reason, ?Request $request = null): void
    {
        if ($session->revoked_at !== null) {
            return;
        }

        DB::transaction(function () use ($session, $actor, $reason, $request): void {
            $locked = SecuritySession::query()->lockForUpdate()->findOrFail($session->getKey());

            if ($locked->revoked_at !== null) {
                return;
            }

            if ($locked->guard === 'sanctum') {
                if ($locked->token_id !== null) {
                    $locked->user->tokens()->whereKey($locked->token_id)->delete();
                }
            } elseif ($locked->guard === 'web') {
                if (config('session.driver') !== 'database') {
                    throw ValidationException::withMessages([
                        'session' => 'La révocation serveur des sessions web exige le pilote database.',
                    ]);
                }

                $sessionId = $locked->identifier_ciphertext;

                if (is_string($sessionId) && $sessionId !== '') {
                    DB::table(config('session.table', 'sessions'))->where('id', $sessionId)->delete();
                }
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $actor->getKey(),
                'revoke_reason' => mb_substr(trim($reason), 0, 500),
            ])->save();

            app(AuditLogService::class)->recordEvent(
                'security.session.revoked',
                $locked,
                $actor,
                ['guard' => $locked->guard, 'target_user_id' => $locked->user_id],
                $request,
                category: 'security',
                reason: $reason,
                severity: 'medium',
            );
        }, attempts: 3);
    }
}
