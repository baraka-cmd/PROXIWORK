# Audit de récupération — migrations et seeders PROXIWORK

**Date :** 11 novembre 2026  
**Branche de référence :** `main-proxiwork`  
**Commit audité :** `844d0d14ad130fd20f0d1322cd1f6b3b0738e011`  
**Branche de correction :** `audit/pre-recovery-database-cleanup`

## 1. Inventaire vérifié

- 103 fichiers de migration dans `database/migrations/`.
- 5 seeders : `DatabaseSeeder`, `RbacSeeder`, `FaqCategorySeeder`, `CategorySeeder`, `DemoPlatformSeeder`.
- 7 fichiers de factories dans `database/factories/` (dont plusieurs factories métier).
- `DatabaseSeeder` exécute les seeders dans cet ordre : RBAC, catégories FAQ, catégories du catalogue, données de démonstration.

## 2. Ordre des migrations et clés étrangères

La branche de référence conserve plusieurs préfixes date/heure identiques. Laravel trie les migrations à partir du nom complet du fichier ; les suffixes donnent donc un ordre déterministe. Les préfixes partagés ne sont pas, à eux seuls, une preuve d'erreur.

La migration de favoris du début de séquence est un placeholder historique volontairement vide. La vraie création de `favorites` est faite par `2026_10_06_000011_create_favorites_table.php`, après `professional_profiles`. Ce choix conserve la compatibilité avec les bases qui ont déjà enregistré le nom historique dans la table `migrations`.

**Ne pas renommer, supprimer ou réordonner les migrations historiques déjà publiées.** Une migration déjà enregistrée dans une base est identifiée par son nom. Un renommage peut provoquer une nouvelle tentative d'exécution et casser les installations existantes. Toute correction de schéma doit être ajoutée sous forme d'une nouvelle migration corrective.

### Résultat de l'exécution automatisée

Le workflow CI contient un job MySQL 8.4 qui exécute successivement :

1. `php artisan migrate --force` sur une base vierge ;
2. `php artisan db:seed --force` ;
3. un second `php artisan db:seed --force`.

Sur l'exécution CI consultée pour le commit audité, l'étape de migrations MySQL vierges et les deux exécutions de seeders ont réussi. Cela valide l'ordre de création du schéma et la répétabilité observée des seeders sur MySQL 8.4 pour ce commit.

Cela ne constitue pas une preuve d'exécution sur MariaDB 10.4 / XAMPP, qui reste l'environnement local à contrôler.

## 3. Analyse des seeders

### Ordre et responsabilités

- `RbacSeeder` crée/met à jour les permissions et rôles, puis synchronise les permissions des rôles. Il doit précéder les comptes de démonstration.
- `FaqCategorySeeder` prépare les catégories de FAQ.
- `CategorySeeder` crée les catégories racines et leurs sous-catégories avec des clés métier stables.
- `DemoPlatformSeeder` utilise les utilisateurs, rôles et catégories déjà créés. Il ajoute des données liées : profils, compétences, services, favoris, demandes, devis, commandes, paiements simulés, portefeuilles, avis, messagerie, notifications, support, modération et audit.

Les seeders principaux utilisent des clés stables et `updateOrCreate` / `updateOrInsert`, ce qui permet leur répétition sans dupliquer les principaux enregistrements de démonstration. La CI exécute effectivement deux fois `db:seed`.

### Protection des données de démonstration

Les comptes et données de démonstration ne doivent jamais être insérés accidentellement en staging ou en production. La branche de correction ajoute une protection dans `DemoPlatformSeeder` : exécution autorisée uniquement avec `APP_ENV=local` ou `APP_ENV=testing`. Dans les autres environnements, le seeder est ignoré avec un avertissement.

Les identifiants documentés dans `docs/local-demo-seeding.md` sont exclusivement locaux. Les paiements de démonstration utilisent le fournisseur fictif `fake` et ne doivent pas être interprétés comme des transactions réelles.

## 4. Limites des données de démonstration

Le seeder actuel fournit une bonne base de parcours intégrés, mais ne couvre pas à lui seul toutes les combinaisons de tests administratifs. En particulier, les jeux de données ne doivent pas être considérés comme exhaustifs pour :

- tous les états de vérification (en attente, rejeté, documents incomplets, réexamen) ;
- toutes les combinaisons de suspension, désactivation et réactivation ;
- chaque état de litige, remboursement et retrait ;
- tous les états de modération et de livraison de notifications ;
- les erreurs techniques, les jobs échoués et les incidents de production.

Ces scénarios doivent être créés de préférence par des factories et seeders de scénarios isolés, avec des clés déterministes et une protection d'environnement identique. Ne pas enrichir aveuglément le seeder principal avec des données qui rendraient les tests non déterministes ou modifieraient des données métier réelles.

## 5. État de validation global

| Contrôle | Résultat |
|---|---|
| Inventaire des migrations | 103 fichiers recensés |
| Inventaire des seeders | 5 fichiers recensés |
| Migration MySQL vierge CI | Réussie sur l'exécution consultée |
| Deux exécutions successives de seeders CI | Réussies sur l'exécution consultée |
| Préfixes de migration dupliqués | Présents, ordre déterministe ; ne pas renommer l'historique |
| Compatibilité MariaDB 10.4 locale | À confirmer par le propriétaire du projet |
| Suite PHPUnit complète | Non validée : des tests CI ont arrêté prématurément le processus PHP |
| Parcours fonctionnels dans le navigateur | À tester localement |

## 6. Procédure locale recommandée

Sur une base de développement vide uniquement :

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
```

Pour contrôler la répétabilité sans détruire la base :

```bash
php artisan db:seed
php artisan db:seed
```

Pour vérifier l'état des migrations :

```bash
php artisan migrate:status
```

**Attention :** `migrate:fresh` supprime toutes les tables de la base configurée. Vérifier soigneusement le nom de la base dans `.env` et faire une sauvegarde avant toute exécution.

## Verdict

L'ordre actuel des migrations est suffisamment confirmé par l'exécution MySQL vierge du pipeline CI ; aucun renommage historique n'est recommandé. Les seeders existants couvrent déjà un parcours de démonstration large et leur répétition a été vérifiée en CI. La branche de correction ajoute une protection pour empêcher l'insertion des données fictives hors des environnements local/testing.

La récupération du dépôt est possible, mais la validation finale doit encore inclure les essais sur l'environnement local MariaDB/XAMPP et la résolution des échecs PHPUnit signalés dans le pipeline.
