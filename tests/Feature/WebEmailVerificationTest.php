<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WebEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_unverified_user_can_view_notice_and_request_another_link(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();
        $user->assignRole('client');

        $this->actingAs($user)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertViewIs('auth.verify-email')
            ->assertSee($user->email);

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_signed_web_link_verifies_email_and_records_audit_event(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('client');

        $url = URL::temporarySignedRoute(
            'web.verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('status');

        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.web.email_verified',
        ]);
    }

    public function test_signed_link_with_another_email_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('client');

        $url = URL::temporarySignedRoute(
            'web.verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1('someone-else@example.com')],
        );

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_is_not_available_to_another_authenticated_user(): void
    {
        $owner = User::factory()->unverified()->create();
        $owner->assignRole('client');
        $other = User::factory()->create();
        $other->assignRole('client');

        $url = URL::temporarySignedRoute(
            'web.verification.verify',
            now()->addMinutes(10),
            ['id' => $owner->id, 'hash' => sha1($owner->getEmailForVerification())],
        );

        $this->actingAs($other)->get($url)->assertForbidden();
        $this->assertNull($owner->fresh()->email_verified_at);
    }
}
