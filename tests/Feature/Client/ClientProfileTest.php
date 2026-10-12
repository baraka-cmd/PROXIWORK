<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_is_redirected_to_login_from_client_profile(): void
    {
        $this->get('/client/profile')->assertRedirect('/login');
    }

    public function test_non_client_cannot_open_client_profile(): void
    {
        $professional = User::factory()->create();
        $professional->assignRole('professional');

        $this->actingAs($professional)->get('/client/profile')->assertForbidden();
    }

    public function test_client_can_view_only_their_own_profile_information(): void
    {
        $client = $this->client();
        $client->profile()->create([
            'first_name' => 'Aline',
            'last_name' => 'Mukendi',
            'phone' => '+243800000001',
            'bio' => 'Présentation privée du compte.',
        ]);

        $otherClient = $this->client();
        $otherClient->profile()->create([
            'first_name' => 'Autre',
            'last_name' => 'Client',
            'bio' => 'Texte qui ne doit pas apparaître.',
        ]);

        $this->actingAs($client)
            ->get('/client/profile')
            ->assertOk()
            ->assertViewIs('client.profile.show')
            ->assertSee('Aline')
            ->assertSee('Mukendi')
            ->assertSee('+243800000001')
            ->assertDontSee('Texte qui ne doit pas apparaître.');
    }

    public function test_profile_update_changes_only_explicitly_allowed_profile_fields(): void
    {
        $client = $this->client();
        $client->profile()->create(['first_name' => 'Ancien', 'last_name' => 'Nom']);
        $verifiedAt = $client->email_verified_at;

        $this->actingAs($client)
            ->patch('/client/profile', [
                'first_name' => '  Baraka  ',
                'last_name' => 'Ntwali',
                'phone' => '+243800000002',
                'bio' => 'Profil mis à jour.',
                'role' => 'admin',
                'account_status' => 'suspended',
                'email_verified_at' => null,
                'user_id' => 99999,
            ])
            ->assertRedirect('/client/profile')
            ->assertSessionHas('success');

        $client->refresh();
        $this->assertSame('client', $client->roles()->value('name'));
        $this->assertNotSame('admin', $client->roles()->value('name'));
        $this->assertTrue($client->isActive());
        $this->assertEquals($verifiedAt, $client->email_verified_at);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $client->id,
            'first_name' => 'Baraka',
            'last_name' => 'Ntwali',
            'phone' => '+243800000002',
        ]);
    }

    public function test_invalid_profile_data_is_rejected_without_success_message(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->patch('/client/profile', [
                'first_name' => '',
                'last_name' => str_repeat('N', 101),
                'phone' => '+243800000003',
            ])
            ->assertSessionHasErrors(['first_name', 'last_name'])
            ->assertSessionMissing('success');
    }

    public function test_client_can_upload_and_read_only_their_own_private_avatar(): void
    {
        Storage::fake('local');
        $client = $this->client();
        $otherClient = $this->client();

        $this->actingAs($client)
            ->post('/client/profile/photo', [
                'avatar' => UploadedFile::fake()->image('avatar.png', 400, 400)->size(500),
            ])
            ->assertRedirect('/client/profile/edit')
            ->assertSessionHas('success');

        $profile = $client->fresh()->profile;
        $this->assertNotNull($profile?->avatar_path);
        Storage::disk('local')->assertExists($profile->avatar_path);

        $this->actingAs($client)
            ->get('/client/profile/photo')
            ->assertOk()
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($otherClient)->get('/client/profile/photo')->assertNotFound();
    }

    public function test_invalid_avatar_does_not_replace_the_existing_photo(): void
    {
        Storage::fake('local');
        $client = $this->client();
        $oldPath = UploadedFile::fake()->image('old.png')->store('client-avatars/'.$client->id, 'local');
        $client->profile()->create(['first_name' => 'Client', 'last_name' => 'Test', 'avatar_path' => $oldPath]);

        $this->actingAs($client)
            ->from('/client/profile/edit')
            ->post('/client/profile/photo', [
                'avatar' => UploadedFile::fake()->create('malware.php', 10, 'application/x-php'),
            ])
            ->assertRedirect('/client/profile/edit')
            ->assertSessionHasErrors('avatar');

        $this->assertSame($oldPath, $client->fresh()->profile?->avatar_path);
        Storage::disk('local')->assertExists($oldPath);
    }

    public function test_password_change_requires_current_password_and_revokes_api_tokens(): void
    {
        Notification::fake();
        $client = $this->client();
        $token = $client->createToken('profile-test-token');

        $this->actingAs($client)
            ->patch('/client/profile/password', [
                'current_password' => 'wrong-current-password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $client->fresh()->password));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);

        $this->actingAs($client)
            ->patch('/client/profile/password', [
                'current_password' => 'password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect('/client/profile/edit')
            ->assertSessionHas('success');

        $client->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $client->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        Notification::assertSentTo($client, AccountActivityNotification::class);
    }

    public function test_password_change_rejects_a_weak_or_unconfirmed_password(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->patch('/client/profile/password', [
                'current_password' => 'password',
                'password' => 'weak',
                'password_confirmation' => 'different',
            ])
            ->assertSessionHasErrors(['password']);

        $this->assertTrue(Hash::check('password', $client->fresh()->password));
    }

    private function client(): User
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        return $user;
    }
}
