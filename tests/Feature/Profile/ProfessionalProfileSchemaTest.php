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

    public function test_professional_profile_has_all_marketplace_profile_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('professional_profiles', [
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
            'starting_price' => 25.00,
            'currency' => 'USD',
            'province' => 'Nord-Kivu',
            'city' => 'Goma',
            'commune' => 'Goma',
            'service_radius_km' => 20,
            'latitude' => -1.6792,
            'longitude' => 29.2228,
        ]);

        $this->assertSame('Développeur Laravel', $profile->fresh()->professional_title);
        $this->assertSame('Développement web et API.', $profile->fresh()->description);
        $this->assertSame('Goma', $profile->fresh()->city);
        $this->assertSame('USD', $profile->fresh()->currency);
        $this->assertSame('draft', $profile->fresh()->status);
        $this->assertSame('private', $profile->fresh()->visibility);
    }
}
