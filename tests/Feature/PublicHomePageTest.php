<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PublicHomePageTest extends TestCase
{
    public function test_homepage_renders_the_proxiwork_public_experience(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('public.home')
            ->assertSee('Trouvez les bons professionnels')
            ->assertSee('Les bonnes compétences.')
            ->assertSee('Découvrir les professionnels')
            ->assertSee('COMMENT ÇA MARCHE')
            ->assertSee('name="search"', false)
            ->assertSee('role="search"', false)
            ->assertDontSee('Laravel has an incredibly rich ecosystem');
    }
}
