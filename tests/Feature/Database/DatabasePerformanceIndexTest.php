<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabasePerformanceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_high_frequency_query_indexes_exist(): void
    {
        $this->assertTrue(
            Schema::hasIndex('users', ['account_status', 'created_at'])
        );

        $this->assertTrue(
            Schema::hasIndex('addresses', ['user_id', 'is_default', 'city', 'province'])
        );

        $this->assertTrue(
            Schema::hasIndex('services', ['professional_profile_id', 'status', 'published_at'])
        );
    }
}
