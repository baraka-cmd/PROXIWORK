<?php

declare(strict_types=1);

namespace Tests\Feature\Profile;

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
    }
}
