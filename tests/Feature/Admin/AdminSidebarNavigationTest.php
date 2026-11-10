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
            ->assertSee('Prestataires professionnels')
            ->assertSee('Comptes suspendus')
            ->assertSee('Dossiers professionnels')
            ->assertSee('Catégories')
            ->assertSee('Compétences')
            ->assertSee('Services')
            ->assertSee('Demandes de services')
            ->assertSee('Commandes')
            ->assertSee('Paiements')
            ->assertSee('Transactions')
            ->assertSee('Commissions')
            ->assertSee('Portefeuilles professionnels')
            ->assertSee('Retraits')
            ->assertSee('Remboursements')
            ->assertSee('Mouvements financiers')
            ->assertSee('Rapports financiers')
            ->assertSee('Litiges commerciaux')
            ->assertSee('Avis et évaluations')
            ->assertSee('Historique de modération')
            ->assertSee('Signalements')
            ->assertSee('Support et réclamations')
            ->assertSee('Statistiques générales')
            ->assertSee('Journal des activités')
            ->assertDontSee('Aucune page de gestion disponible pour le moment.')
            ->assertSee('Communication et contenus')
            ->assertSee('Notifications et livraisons')
            ->assertDontSee('Configuration');
    }
}
