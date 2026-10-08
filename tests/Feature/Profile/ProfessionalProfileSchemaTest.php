<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

use App\Models\ProfessionalProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProfessionalProfileSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_profile_contains_all_fields_used_by_the_model(): void
    {
        $this->assertTrue(Schema::hasColumns('professional_profiles', [
            'user_id',
            'professional_title',
            'description',
            'years_experience',
            'starting_price',
            'currency',
            'province',
            'city',
            'commune',
            'service_radius_km',
            'latitude',
            'longitude',
            'status',
            'visibility',
            'verification_status',
            'availability_status',
            'rating_average',
            'rating_count',
            'verified_at',
        ]));

        $profile = ProfessionalProfile::factory()->create([
            'professional_title' => 'Développeur Laravel',
            'description' => 'Développement web et API.',
            'years_experience' => 4,
            'starting_price' => '25.00',
            'currency' => 'USD',
            'province' => 'Nord-Kivu',
            'city' => 'Goma',
            'commune' => 'Karisimbi',
            'service_radius_km' => 20,
            'latitude' => '-1.6792000',
            'longitude' => '29.2228000',
            'status' => 'active',
            'visibility' => 'public',
        ])->fresh();

        $this->assertSame('Développeur Laravel', $profile->professional_title);
        $this->assertSame('Développement web et API.', $profile->description);
        $this->assertSame(4, $profile->years_experience);
        $this->assertSame('Goma', $profile->city);
        $this->assertSame('Nord-Kivu', $profile->province);
        $this->assertSame('USD', $profile->currency);
        $this->assertSame('25.00', $profile->starting_price);
        $this->assertSame('active', $profile->status);
        $this->assertSame('public', $profile->visibility);
    }
}
