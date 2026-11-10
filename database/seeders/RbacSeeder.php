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
            'services.manage' => ['Services - administrer', 'services', 'Gérer ses propres services.'],
            'admin.services.view' => ['Administration - services consulter', 'admin.services', 'Consulter tous les services et leurs preuves de réalisation.'],
            'admin.services.manage' => ['Administration - services administrer', 'admin.services', 'Publier, dépublier et archiver les services.'],
            'admin.services.moderate' => ['Administration - services modérer', 'admin.services', 'Examiner, approuver, masquer, rejeter ou demander des corrections pour les services.'],
            'admin.service_realizations.moderate' => ['Administration - réalisations modérer', 'admin.services', 'Approuver ou rejeter les preuves de réalisation.'],
            'requests.view' => ['Demandes - consulter', 'requests', 'Consulter les demandes de service.'],
            'requests.manage' => ['Demandes - administrer', 'requests', 'Gérer les demandes de service.'],
            'orders.view' => ['Commandes - consulter', 'orders', 'Consulter les commandes.'],
            'orders.manage' => ['Commandes - administrer', 'orders', 'Gérer les commandes.'],
            'payments.view' => ['Paiements - consulter', 'payments', 'Consulter les paiements.'],
            'payments.manage' => ['Paiements - administrer', 'payments', 'Gérer les paiements.'],
            'admin.withdrawals.manage' => ['Administration - retraits financiers', 'admin.finance', 'Examiner, traiter, rapprocher et décider les retraits professionnels.'],
            'reviews.moderate' => ['Avis - modérer', 'reviews', 'Modérer les avis.'],
            'reports.manage' => ['Signalements - traiter', 'reports', 'Traiter les signalements.'],
            'disputes.view' => ['Litiges - consulter', 'disputes', 'Consulter les dossiers de litiges commerciaux.'],
            'disputes.manage' => ['Litiges - administrer', 'disputes', 'Attribuer et faire avancer les dossiers de litiges.'],
            'disputes.decide' => ['Litiges - décider', 'disputes', 'Enregistrer les décisions motivées sur les litiges.'],
            'moderation.history.view' => ['Modération - historique et résolutions consulter', 'moderation', 'Consulter l’historique et les indicateurs de résolution.'],
            'support.manage' => ['Support - administrer', 'support', 'Gérer les tickets de support.'],
            'audit.view' => ['Audit - consulter', 'audit', 'Consulter les journaux d’audit.'],
            'admin.dashboard.view' => ['Dashboard administrateur - consulter', 'admin', 'Consulter les statistiques administratives.'],
            'admin.users.view' => ['Administration - utilisateurs consulter', 'admin.users', 'Consulter, rechercher et filtrer les utilisateurs.'],
            'admin.users.suspend' => ['Administration - utilisateurs suspendre', 'admin.users', 'Suspendre un compte utilisateur.'],
            'admin.users.activate' => ['Administration - utilisateurs activer', 'admin.users', 'Réactiver un compte utilisateur.'],
            'admin.professionals.view' => ['Administration - professionnels consulter', 'admin.professionals', 'Consulter les dossiers professionnels.'],
            'admin.professionals.suspend' => ['Administration - professionnels suspendre', 'admin.professionals', 'Suspendre un compte professionnel.'],
            'admin.professionals.activate' => ['Administration - professionnels activer', 'admin.professionals', 'Réactiver un compte professionnel.'],
            'admin.professionals.review' => ['Administration - professionnels revoir', 'admin.professionals', 'Placer une vérification professionnelle en revue.'],
            'admin.professionals.verify' => ['Administration - professionnels vérifier', 'admin.professionals', 'Valider une vérification professionnelle.'],
            'admin.professionals.reject' => ['Administration - professionnels rejeter', 'admin.professionals', 'Rejeter une vérification professionnelle.'],
            'notifications.view' => ['Notifications - consulter', 'communication', 'Consulter les notifications et leurs livraisons.'],
            'notifications.retry' => ['Notifications - relancer', 'communication', 'Relancer une livraison explicitement échouée.'],
            'notification_templates.view' => ['Modèles - consulter', 'communication', 'Consulter les modèles de notifications.'],
            'notification_templates.manage' => ['Modèles - modifier', 'communication', 'Créer et versionner les modèles.'],
            'notification_templates.publish' => ['Modèles - publier', 'communication', 'Activer un modèle de notification.'],
            'announcements.view' => ['Annonces - consulter', 'communication', 'Consulter les annonces.'],
            'announcements.manage' => ['Annonces - administrer', 'communication', 'Créer les annonces.'],
            'announcements.publish' => ['Annonces - publier', 'communication', 'Publier les annonces.'],
            'announcements.archive' => ['Annonces - archiver', 'communication', 'Archiver les annonces.'],
            'communications.view' => ['Campagnes - consulter', 'communication', 'Consulter les campagnes ciblées.'],
            'communications.manage' => ['Campagnes - administrer', 'communication', 'Créer les campagnes et définir les audiences.'],
            'communications.publish' => ['Campagnes - diffuser', 'communication', 'Déclencher une diffusion ciblée.'],
            'public_pages.view' => ['Pages publiques - consulter', 'content', 'Consulter les pages publiques.'],
            'public_pages.manage' => ['Pages publiques - modifier', 'content', 'Créer et modifier les pages publiques.'],
            'public_pages.publish' => ['Pages publiques - publier', 'content', 'Publier les pages publiques.'],
            'faq.view' => ['FAQ - consulter', 'content', 'Consulter la FAQ.'],
            'faq.manage' => ['FAQ - administrer', 'content', 'Gérer les catégories et questions FAQ.'],
            'institutional_content.view' => ['Contenus institutionnels - consulter', 'content', 'Consulter les contenus institutionnels.'],
            'institutional_content.manage' => ['Contenus institutionnels - modifier', 'content', 'Créer et versionner les contenus institutionnels.'],
            'institutional_content.publish' => ['Contenus institutionnels - publier', 'content', 'Publier les contenus institutionnels.'],
            'homepage_slides.view' => ['Diapositives - consulter', 'content', 'Consulter les diapositives de la page d’accueil.'],
            'homepage_slides.manage' => ['Diapositives - administrer', 'content', 'Créer et archiver les diapositives.'],
            'homepage_slides.publish' => ['Diapositives - publier', 'content', 'Publier les diapositives.'],
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
                'requests.view', 'orders.view', 'reviews.moderate', 'reports.manage', 'disputes.view', 'disputes.manage', 'moderation.history.view', 'admin.services.view', 'admin.services.moderate', 'admin.service_realizations.moderate',
            ]],
            'support' => ['Support', 'Assure l’assistance et le suivi des utilisateurs.', true, [
                'users.view', 'profiles.view', 'categories.view', 'skills.view', 'services.view',
                'requests.view', 'orders.view', 'payments.view', 'support.manage', 'disputes.view',
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
