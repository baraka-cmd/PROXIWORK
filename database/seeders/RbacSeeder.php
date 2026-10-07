<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'rbac.view' => ['RBAC - consulter', 'rbac', 'Consulter les rôles et permissions.'],
            'rbac.manage' => ['RBAC - administrer', 'rbac', 'Créer, modifier et supprimer les rôles et leurs permissions.'],
            'users.view' => ['Utilisateurs - consulter', 'users', 'Consulter les utilisateurs.'],
            'users.manage' => ['Utilisateurs - administrer', 'users', 'Gérer les utilisateurs et leurs rôles.'],
            'profiles.view' => ['Profils - consulter', 'profiles', 'Consulter les profils.'],
            'profiles.manage' => ['Profils - administrer', 'profiles', 'Gérer les profils.'],
            'categories.view' => ['Catégories - consulter', 'categories', 'Consulter le catalogue des catégories.'],
            'categories.manage' => ['Catégories - administrer', 'categories', 'Créer, modifier, désactiver et archiver les catégories.'],
            'skills.view' => ['Compétences - consulter', 'skills', 'Consulter le catalogue des compétences.'],
            'skills.manage' => ['Compétences - administrer', 'skills', 'Créer, modifier et archiver les compétences.'],
            'professional_skills.manage' => ['Compétences professionnelles - gérer', 'skills', 'Associer et retirer les compétences de son profil professionnel.'],
            'services.view' => ['Services - consulter', 'services', 'Consulter les services publiés.'],
            'services.manage' => ['Services - administrer', 'services', 'Gérer les services.'],
            'requests.view' => ['Demandes - consulter', 'requests', 'Consulter les demandes de service.'],
            'requests.manage' => ['Demandes - administrer', 'requests', 'Gérer les demandes de service.'],
            'orders.view' => ['Commandes - consulter', 'orders', 'Consulter les commandes.'],
            'orders.manage' => ['Commandes - administrer', 'orders', 'Gérer les commandes.'],
            'payments.view' => ['Paiements - consulter', 'payments', 'Consulter les paiements.'],
            'payments.manage' => ['Paiements - administrer', 'payments', 'Gérer les paiements.'],
            'reviews.moderate' => ['Avis - modérer', 'reviews', 'Modérer les avis.'],
            'reports.manage' => ['Signalements - traiter', 'reports', 'Traiter les signalements.'],
            'support.manage' => ['Support - administrer', 'support', 'Gérer les tickets de support.'],
            'audit.view' => ['Audit - consulter', 'audit', 'Consulter les journaux d’audit.'],
            'admin.dashboard.view' => ['Dashboard administrateur - consulter', 'admin', 'Consulter les statistiques administratives.'],
        ];

        foreach ($permissions as $name => [$displayName, $group, $description]) {
            Permission::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $displayName,
                    'group' => $group,
                    'description' => $description,
                ],
            );
        }

        $roles = [
            'client' => ['Client', 'Utilisateur qui recherche et commande des services.', false, [
                'profiles.view', 'profiles.manage', 'categories.view', 'skills.view', 'services.view',
                'requests.view', 'requests.manage', 'orders.view',
            ]],
            'professional' => ['Professionnel', 'Utilisateur qui propose et réalise des services.', false, [
                'profiles.view', 'profiles.manage', 'categories.view', 'skills.view', 'professional_skills.manage', 'services.view', 'services.manage',
                'requests.view', 'requests.manage', 'orders.view', 'orders.manage',
            ]],
            'moderator' => ['Modérateur', 'Gère la qualité des contenus et les signalements.', true, [
                'rbac.view', 'users.view', 'profiles.view', 'categories.view', 'skills.view', 'services.view',
                'requests.view', 'orders.view', 'reviews.moderate', 'reports.manage',
            ]],
            'support' => ['Support', 'Assure l’assistance et le suivi des utilisateurs.', true, [
                'users.view', 'profiles.view', 'categories.view', 'skills.view', 'services.view',
                'requests.view', 'orders.view', 'payments.view', 'support.manage', 'reports.manage',
            ]],
            'admin' => ['Administrateur', 'Administration complète de la plateforme.', true, null],
        ];

        foreach ($roles as $name => [$displayName, $description, $isSystem, $permissionNames]) {
            $role = Role::updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $displayName,
                    'description' => $description,
                    'is_system' => $isSystem,
                ],
            );

            $permissionIds = $permissionNames === null
                ? Permission::query()->pluck('id')
                : Permission::whereIn('name', $permissionNames)->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
