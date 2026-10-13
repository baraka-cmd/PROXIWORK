<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\ProfessionalVerificationStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicProfessionalAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_is_served_only_when_professional_is_publicly_visible(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['account_status' => 'active']);
        $avatar = UploadedFile::fake()->image('avatar.jpg');
        $path = $avatar->store('professional-avatars/'.$user->getKey(), 'local');
        $user->profile()->create([
            'first_name' => 'Professionnel',
            'last_name' => 'Visible',
            'avatar_path' => $path,
        ]);

        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $user->getKey(),
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
        ]);

        $category = Category::factory()->create(['status' => 'active']);
        Service::factory()->create([
            'professional_profile_id' => $professional->getKey(),
            'category_id' => $category->getKey(),
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->get(route('public.professionals.photo', $professional))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_avatar_is_not_served_when_profile_is_private(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['account_status' => 'active']);
        $path = UploadedFile::fake()->image('avatar.jpg')
            ->store('professional-avatars/'.$user->getKey(), 'local');
        $user->profile()->create([
            'first_name' => 'Professionnel',
            'last_name' => 'Privé',
            'avatar_path' => $path,
        ]);

        $professional = ProfessionalProfile::factory()->create([
            'user_id' => $user->getKey(),
            'status' => ProfessionalProfile::STATUS_ACTIVE,
            'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
            'verification_status' => ProfessionalVerificationStatus::VERIFIED,
        ]);

        $this->get(route('public.professionals.photo', $professional))
            ->assertNotFound();
    }
}
