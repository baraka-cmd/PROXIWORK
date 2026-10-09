<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_open_password_reset_request_page(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertViewIs('auth.passwords.email')
            ->assertSee('Mot de passe oublié');
    }

    public function test_existing_user_receives_a_password_reset_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'client@example.com']);

        $this->post(route('password.email'), ['email' => 'CLIENT@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_generic_confirmation_without_sending_notification(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_valid_reset_token_changes_password_and_invalidates_old_token(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'client@example.com',
            'password' => 'OldPassword123!',
        ]);

        $this->post(route('password.email'), ['email' => $user->email])->assertRedirect();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->assertIsString($token);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertViewIs('auth.passwords.reset');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewPassword456!', $user->fresh()->password));

        $this->from(route('password.request'))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'AnotherPassword789!',
                'password_confirmation' => 'AnotherPassword789!',
            ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
    }

    public function test_invalid_or_weak_password_reset_submission_is_rejected(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => 'client@example.com',
                'password' => 'weak',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['password']);
    }
}
