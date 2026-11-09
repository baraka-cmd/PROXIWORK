<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_only_displays_existing_and_authorized_destinations(): void
    {
        $this->seed(RbacSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(Role::query()->where('name', 'admin')->firstOrFail());

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('data-admin-navigation', false)
            ->assertSee('href="'.route('admin.users.index').'"', false)
            ->assertSee('href="'.route('admin.users.index', ['role' => 'client']).'"', false)
            ->assertSee('href="'.route('admin.users.index', ['account_status' => 'suspended']).'"', false)
            ->assertSee('Tous les utilisateurs')
            ->assertSee('Clients')
            ->assertSee('Professionnels')
            ->assertSee('Comptes suspendus')
            ->assertSee('Dossiers professionnels')
            ->assertSee('Dossiers en attente')
            ->assertSee('Catégories')
            ->assertSee('Compétences')
            ->assertSee('Services publiés')
            ->assertSee('Demandes de services')
            ->assertSee('Commandes')
            ->assertSee('Paiements et transactions')
            ->assertSee('Retraits')
            ->assertSee('Signalements')
            ->assertSee('Support et réclamations')
            ->assertSee('Statistiques générales')
            ->assertSee('Journal des activités')
            ->assertDontSee('Aucune page de gestion disponible pour le moment.')
            ->assertDontSee('Communication')
            ->assertDontSee('Configuration');
    }
}
