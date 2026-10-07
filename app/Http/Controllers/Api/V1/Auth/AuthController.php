<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Enums\UserAccountStatus;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => mb_strtolower($request->string('email')->toString()),
                'password' => $request->string('password')->toString(),
            ]);

            $user->profile()->create();
            $user->notificationPreference()->create();
            $user->assignRole(Role::where('name', 'client')->firstOrFail());

            return $user;
        });

        event(new Registered($user));
        $this->auditLogService->record('register', $user, $user, [], $request);
        $this->auditLogService->record('role_assigned', $user, $user, ['role' => 'client'], $request);

        $token = $user->createToken(
            $request->string('device_name')->toString(),
            ['*'],
            now()->addMinutes((int) config('sanctum.expiration', 43200))
        )->plainTextToken;

        return response()->json([
            'message' => 'Compte créé. Vérifiez votre adresse e-mail.',
            'data' => [
                'user' => new UserResource($user),
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

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw new AuthenticationException('Identifiants invalides.');
        }

        if ($user->account_status === UserAccountStatus::SUSPENDED) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Compte suspendu.');
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
                'user' => new UserResource($user),
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

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update([
            'password' => $request->string('password')->toString(),
        ]);

        $user->tokens()->delete();

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

        $status = Password::sendResetLink([
            'email' => mb_strtolower($request->string('email')->toString()),
        ]);

        if ($status !== Password::ResetLinkSent) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
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
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $this->auditLogService->record('password_reset', $user, $user, [], $request);
        $user->notify(new AccountActivityNotification(
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

        return response()->json([
            'message' => 'Un nouveau lien de vérification a été envoyé.',
            'data' => null,
            'meta' => [],
        ]);
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
            'Lien de vérification invalide.'
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json([
            'message' => 'Adresse e-mail vérifiée avec succès.',
            'data' => ['user' => new UserResource($user->fresh())],
            'meta' => [],
        ]);
    }
}
