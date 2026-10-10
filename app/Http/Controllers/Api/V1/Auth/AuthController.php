<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\ProfessionalVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly SessionRevocationService $sessionRevocationService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => mb_strtolower($request->string('email')->toString()),
                'password' => $request->string('password')->toString(),
            ]);

            $user->forceFill(['terms_accepted_at' => now()])->save();

            $user->profile()->create();
            $user->notificationPreference()->create();

            $accountType = $request->string('account_type')->toString();
            $user->assignRole(Role::query()->where('name', $accountType)->firstOrFail());

            if ($accountType === 'professional') {
                $professionalProfile = $user->professionalProfile()->create();
                $professionalProfile->forceFill([
                    'status' => 'draft',
                    'visibility' => 'private',
                    'verification_status' => ProfessionalVerificationStatus::PENDING,
                    'professional_terms_accepted_at' => now(),
                ])->save();
            }

            return $user;
        });

        event(new Registered($user));
        $this->auditLogService->record('register', $user, $user, [], $request);
        $this->auditLogService->record('role_assigned', $user, $user, ['role' => $request->string('account_type')->toString()], $request);

        $token = $user->createToken(
            $request->string('device_name')->toString(),
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 43200))
        )->plainTextToken;

        return response()->json([
            'message' => 'Compte créé. Vérifiez votre adresse e-mail.',
            'data' => [
                'account_type' => $request->string('account_type')->toString(),
                'user' => new UserResource($user->load('roles')),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'meta' => [],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = mb_strtolower($request->string('email')->toString());
        $user = User::where('email', $email)->first();

        if ($user === null || Hash::check($request->string('password')->toString(), $user->password) === false) {
            throw new AuthenticationException('Identifiants invalides.');
        }

        if ($user->isActive() === false) {
            throw new AccessDeniedHttpException('Compte suspendu.');
        }

        $token = $user->createToken(
            $request->string('device_name')->toString(),
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 43200))
        )->plainTextToken;

        $this->auditLogService->record('login', $user, $user, [], $request);

        return response()->json([
            'message' => 'Connexion réussie.',
            'data' => [
                'user' => new UserResource($user->load('roles')),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'meta' => [],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        $this->auditLogService->record('logout', $request->user(), $request->user(), [], $request);

        return response()->json([
            'message' => 'Déconnexion réussie.',
            'data' => null,
            'meta' => [],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Utilisateur authentifié.',
            'data' => new UserResource($request->user()),
            'meta' => [],
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (Hash::check($request->string('current_password')->toString(), $user->password) === false) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update([
            'password' => $request->string('password')->toString(),
        ]);

        $this->sessionRevocationService->revokeAll($user);

        $this->auditLogService->record('password_changed', $user, $user, [], $request);
        $user->notify(new AccountActivityNotification(
            'Mot de passe modifié',
            'Votre mot de passe a été modifié et vos anciennes sessions ont été révoquées.',
            'password_changed',
        ));

        $token = $user->createToken(
            $request->string('device_name')->toString(),
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 43200))
        )->plainTextToken;

        return response()->json([
            'message' => 'Mot de passe modifié. Les anciennes sessions ont été révoquées.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            'meta' => [],
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc']]);

        try {
            Password::sendResetLink([
                'email' => mb_strtolower($request->string('email')->toString()),
            ]);
        } catch (Throwable $exception) {
            Log::warning('API password reset notification could not be dispatched.', [
                'exception' => $exception::class,
            ]);
        }

        return response()->json([
            'message' => 'Si cette adresse existe, un lien de réinitialisation a été envoyé.',
            'data' => null,
            'meta' => [],
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $resetUser = null;

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resetUser): void {
                $resetUser = $user;

                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $this->sessionRevocationService->revokeAll($user);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET || ($resetUser instanceof User) === false) {
            throw ValidationException::withMessages([
                'email' => ['Le lien de réinitialisation est invalide ou a expiré. Demandez un nouveau lien.'],
            ]);
        }

        $this->auditLogService->record('password_reset', $resetUser, $resetUser, [], $request);
        $resetUser->notify(new AccountActivityNotification(
            'Mot de passe réinitialisé',
            'Votre mot de passe a été réinitialisé avec succès.',
            'password_reset',
        ));

        return response()->json([
            'message' => 'Mot de passe réinitialisé avec succès.',
            'data' => null,
            'meta' => [],
        ]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'L’adresse e-mail est déjà vérifiée.',
                'data' => null,
                'meta' => [],
            ]);
        }

        $request->user()->sendEmailVerificationNotification();
        $this->auditLogService->record('auth.api.email_verification_requested', $request->user(), $request->user(), [], $request);

        return response()->json([
            'message' => 'Un nouveau lien de vérification a été envoyé.',
            'data' => null,
            'meta' => [],
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        abort_unless($user->isActive(), 403, 'Compte suspendu.');

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
            'Lien de vérification invalide.'
        );

        if ($user->hasVerifiedEmail() === false && $user->markEmailAsVerified()) {
            event(new Verified($user));
            $this->auditLogService->record('auth.api.email_verified', $user, $user, [], $request);
        }

        return response()->json([
            'message' => 'Adresse e-mail vérifiée avec succès.',
            'data' => ['user' => new UserResource($user->fresh())],
            'meta' => [],
        ]);
    }
}
