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
            'finance.view' => ['Finances - vue générale', 'admin.finance', 'Consulter les indicateurs consolidés de la situation financière.'],
            'transactions.view' => ['Transactions - consulter', 'admin.finance', 'Consulter les transactions de paiement et leur traçabilité.'],
            'commissions.view' => ['Commissions - consulter', 'admin.finance', 'Consulter les commissions calculées et comptabilisées.'],
            'wallets.view' => ['Portefeuilles - consulter', 'admin.finance', 'Consulter les soldes des portefeuilles professionnels.'],
            'admin.withdrawals.manage' => ['Administration - retraits financiers', 'admin.finance', 'Examiner, traiter, rapprocher et décider les retraits professionnels.'],
            'withdrawals.destination.view' => ['Retraits - révéler les destinations', 'admin.finance', 'Consulter les destinations complètes de versement, avec audit.'],
            'refunds.view' => ['Remboursements - consulter', 'admin.finance', 'Consulter les demandes et résultats de remboursement.'],
            'refunds.manage' => ['Remboursements - examiner', 'admin.finance', 'Prendre en charge ou refuser les demandes de remboursement.'],
            'refunds.execute' => ['Remboursements - confirmer le résultat', 'admin.finance', 'Enregistrer un résultat de remboursement avec preuve externe vérifiée.'],
            'financial_movements.view' => ['Mouvements financiers - consulter', 'admin.finance', 'Consulter le registre immuable des mouvements de portefeuilles.'],
            'financial_reports.view' => ['Rapports financiers - consulter', 'admin.finance', 'Consulter les rapports financiers opérationnels.'],
            'financial_reports.export' => ['Rapports financiers - exporter', 'admin.finance', 'Exporter les lignes financières autorisées en CSV.'],
            'disputes.view' => ['Litiges - consulter', 'disputes', 'Consulter les dossiers de litiges commerciaux.'],
            'disputes.manage' => ['Litiges - administrer', 'disputes', 'Attribuer et faire avancer les dossiers de litiges.'],
            'disputes.decide' => ['Litiges - décider', 'disputes', 'Enregistrer les décisions motivées sur les litiges.'],
            'moderation.history.view' => ['Modération - historique et résolutions consulter', 'moderation', 'Consulter l’historique et les indicateurs de résolution.'],
            'reviews.moderate' => ['Avis - modérer', 'reviews', 'Modérer les avis.'],
            'reports.manage' => ['Signalements - traiter', 'reports', 'Traiter les signalements.'],
            'support.manage' => ['Support - administrer', 'support', 'Gérer les tickets de support.'],
            'audit.view' => ['Audit - consulter', 'audit', 'Consulter les journaux d’audit.'],
            'security.events.view' => ['Sécurité - événements consulter', 'security', 'Consulter les événements de sécurité et d’authentification.'],
            'security.alerts.view' => ['Sécurité - alertes consulter', 'security', 'Consulter les alertes et anomalies détectées.'],
            'security.alerts.manage' => ['Sécurité - alertes traiter', 'security', 'Examiner, qualifier et clôturer les alertes de sécurité.'],
            'security.sessions.view' => ['Sécurité - sessions consulter', 'security', 'Consulter les sessions web et les jetons API actifs.'],
            'security.sessions.revoke' => ['Sécurité - sessions révoquer', 'security', 'Révoquer côté serveur une session ou un jeton compromis.'],
            'audit.interventions.view' => ['Audit - interventions consulter', 'audit', 'Consulter la traçabilité des interventions administratives et financières.'],
            'admin.dashboard.view' => ['Dashboard administrateur - consulter', 'admin', 'Consulter les statistiques administratives.'],
            'analytics.export' => ['Analytics - exporter', 'admin.analytics', 'Exporter les indicateurs analytiques agrégés au format CSV.'],
            'admin.users.view' => ['Administration - utilisateurs consulter', 'admin.users', 'Consulter, rechercher et filtrer les utilisateurs.'],
            'admin.users.suspend' => ['Administration - utilisateurs suspendre', 'admin.users', 'Suspendre un compte utilisateur.'],
            'admin.users.activate' => ['Administration - utilisateurs activer', 'admin.users', 'Réactiver un compte utilisateur.'],
            'admin.professionals.view' => ['Administration - professionnels consulter', 'admin.professionals', 'Consulter les dossiers professionnels.'],
            'admin.professionals.suspend' => ['Administration - professionnels suspendre', 'admin.professionals', 'Suspendre un compte professionnel.'],
            'admin.professionals.activate' => ['Administration - professionnels activer', 'admin.professionals', 'Réactiver un compte professionnel.'],
            'admin.professionals.review' => ['Administration - professionnels revoir', 'admin.professionals', 'Placer une vérification professionnelle en revue.'],
            'admin.professionals.verify' => ['Administration - professionnels vérifier', 'admin.professionals', 'Valider une vérification professionnelle.'],
            'admin.professionals.reject' => ['Administration - professionnels rejeter', 'admin.professionals', 'Rejeter une vérification professionnelle.'],
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
