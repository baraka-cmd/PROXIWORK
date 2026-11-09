<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\User;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\User\AdminUserService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserWebManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_open_user_directory_and_detail(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Utilisateur de contrôle']);
        $user->assignRole('client');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'contrôle']))
            ->assertOk()
            ->assertSee('Tous les utilisateurs')
            ->assertSee('Utilisateur de contrôle');

        $this->actingAs($admin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Informations générales')
            ->assertSee('Activité du client')
            ->assertSee('Historique administratif');
    }

    public function test_summary_cards_preserve_filters_and_neutralize_only_their_own_dimension(): void
    {
        $this->admin();

        $activeClient = User::factory()->create();
        $activeClient->assignRole('client');

        $suspendedClient = User::factory()->suspended()->create();
        $suspendedClient->assignRole('client');

        $activeProfessional = User::factory()->create();
        $activeProfessional->assignRole('professional');

        $summary = app(AdminUserService::class)->overview([
            'role' => 'client',
            'account_status' => 'active',
        ]);

        $this->assertSame(4, $summary['total']);
        $this->assertSame(1, $summary['clients']);
        $this->assertSame(1, $summary['professionals']);
        $this->assertSame(1, $summary['active']);
        $this->assertSame(1, $summary['suspended']);
    }

    public function test_directory_search_matches_a_complete_profile_name(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Compte de test']);
        $user->assignRole('client');
        $user->profile()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Jean Dupont']))
            ->assertOk()
            ->assertSee('Jean Dupont');
    }

    public function test_blank_optional_filters_are_accepted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.users.index', [
                'search' => '',
                'role' => '',
                'account_status' => '',
                'email_verified' => '',
                'verification_status' => '',
                'period' => '',
                'created_from' => '',
                'created_to' => '',
                'location' => '',
                'activity' => '',
                'sort' => '-created_at',
            ]))
            ->assertOk()
            ->assertSee('Tous les utilisateurs');
    }

    public function test_account_scoped_links_open_their_responsible_admin_modules(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($admin)
            ->get(route('admin.service-requests.index', ['client_id' => $user->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['client_id' => $user->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.payments.index', ['client_id' => $user->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.support.index', ['user_id' => $user->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.reports.index', ['reporter_id' => $user->id]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.reports.index', [
                'target_type' => $user->getMorphClass(),
                'target_id' => $user->id,
            ]))
            ->assertOk();
    }

    public function test_user_without_admin_users_permission_cannot_open_directory(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_sensitive_contact_data_is_hidden_without_its_specific_permission(): void
    {
        $admin = $this->admin();
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $sensitivePermission = Permission::query()->where('name', 'admin.users.view_sensitive')->firstOrFail();
        $adminRole->permissions()->detach($sensitivePermission->id);

        $user = User::factory()->create(['name' => 'Compte privé', 'email' => 'prive@example.test']);
        $user->assignRole('client');
        $user->profile()->create([
            'first_name' => 'Compte',
            'last_name' => 'Privé',
            'phone' => '+243990000001',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'Compte privé']))
            ->assertOk()
            ->assertSee('Masqué · permission requise')
            ->assertSee('E-mail masqué')
            ->assertDontSee('+243990000001')
            ->assertDontSee('prive@example.test');

        $this->actingAs($admin)
            ->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertSee('Masqué · permission requise')
            ->assertSee('E-mail masqué · permission requise')
            ->assertDontSee('+243990000001')
            ->assertDontSee('prive@example.test');
    }

    public function test_search_cannot_discover_accounts_by_private_email_or_phone(): void
    {
        $admin = $this->admin();
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $sensitivePermission = Permission::query()->where('name', 'admin.users.view_sensitive')->firstOrFail();
        $adminRole->permissions()->detach($sensitivePermission->id);

        $user = User::factory()->create([
            'name' => 'Compte confidentiel',
            'email' => 'needle.private@example.test',
        ]);
        $user->assignRole('client');
        $user->profile()->create([
            'first_name' => 'Compte',
            'last_name' => 'Confidentiel',
            'phone' => '+243990000002',
        ]);
        $user->addresses()->create([
            'label' => 'Adresse privée',
            'country_code' => 'CD',
            'city' => 'VilleConfidentielle',
            'address_line_1' => 'Adresse non publique',
            'is_default' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'needle.private@example.test']))
            ->assertOk()
            ->assertDontSee('Compte confidentiel');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => '+243990000002']))
            ->assertOk()
            ->assertDontSee('Compte confidentiel');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['location' => 'VilleConfidentielle']))
            ->assertOk()
            ->assertDontSee('Compte confidentiel');
    }

    public function test_admin_can_correct_only_the_profile_name_with_reason_and_audit(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Ancien Nom']);
        $user->assignRole('client');
        $user->profile()->create([
            'first_name' => 'Ancien',
            'last_name' => 'Nom',
        ]);
        $originalEmail = $user->email;

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'first_name' => 'Jean-Marie',
                'last_name' => 'Ndaye',
                'reason' => 'Correction demandée par le titulaire avec justificatif contrôlé.',
                'email' => 'attacker@example.com',
                'account_status' => 'suspended',
                'role' => 'admin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Jean-Marie Ndaye',
            'email' => $originalEmail,
            'account_status' => 'active',
        ]);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'Jean-Marie',
            'last_name' => 'Ndaye',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.identity_updated',
            'subject_id' => $user->id,
        ]);
        $this->assertTrue($user->fresh()->hasRole('client'));
        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    public function test_identity_correction_cannot_modify_self_or_another_administrator(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();

        $payload = [
            'first_name' => 'Nouveau',
            'last_name' => 'Nom',
            'reason' => 'Demande de correction vérifiée auprès du titulaire.',
        ];

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), $payload)
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $otherAdmin), $payload)
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => $admin->name,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $otherAdmin->id,
            'name' => $otherAdmin->name,
        ]);
    }

    public function test_identity_correction_requires_its_specific_permission(): void
    {
        $admin = $this->admin();
        $adminRole = Role::query()->where('name', 'admin')->firstOrFail();
        $permission = Permission::query()->where('name', 'admin.users.update')->firstOrFail();
        $adminRole->permissions()->detach($permission->id);

        $user = User::factory()->create(['name' => 'Nom initial']);
        $user->assignRole('client');

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'first_name' => 'Autre',
                'last_name' => 'Nom',
                'reason' => 'Correction demandée avec une justification suffisamment précise.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nom initial',
        ]);
    }

    public function test_web_admin_can_suspend_and_reactivate_with_a_reason(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $user), [
                'reason_code' => 'policy_violation',
                'duration' => '7d',
                'note' => 'Plusieurs comportements contraires aux règles ont été signalés.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'account_status' => 'suspended',
            'suspension_reason' => 'policy_violation',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.activate', $user), [
                'reason' => 'Le dossier a été examiné et les mesures correctives sont confirmées.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'account_status' => 'active',
            'suspension_reason' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.suspended',
            'subject_id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.user.activated',
            'subject_id' => $user->id,
        ]);
    }

    public function test_web_admin_cannot_suspend_an_administrator_account(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $target->assignRole('admin');

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $target), [
                'reason_code' => 'administrative_decision',
                'duration' => 'until_review',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'account_status' => 'active',
        ]);
    }
}
