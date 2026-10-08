# PROXIWORK — Inventaire de consolidation de la plateforme

## But de cette branche

Cette branche de livraison rassemble l'état consolidé de PROXIWORK afin qu'il puisse être récupéré localement et audité comme un seul projet. Elle part de `phase-3/finalize` au commit de référence `b8892c55de75e0221ffe78d97426f3ec4b6f8ca4`.

**Règle importante :** une branche appelée `final`, `complete` ou `integration` n'est pas considérée complète sur la seule base de son nom. Les changements doivent être vérifiés dans l'arbre de fichiers et par les tests. Ne pas fusionner aveuglément toutes les branches historiques : certaines sont des variantes, des tentatives remplacées ou des PR déjà intégrées.

## Inventaire fonctionnel constaté dans la base consolidée

### Phase 1 — Fondation
- Authentification API avec Sanctum : inscription, connexion, déconnexion, utilisateur courant, changement/réinitialisation du mot de passe et vérification d'e-mail.
- RBAC : rôles, permissions, middleware, policies et endpoints d'administration protégés.
- Profil utilisateur et profil professionnel distincts.
- Adresses : persistance, CRUD API, validation, ownership et adresse par défaut.
- Contrat API versionné, Resources, erreurs et règles de sécurité HTTP.
- Audit logging et notifications de base, avec préférences et contrôle d'accès.

### Phase 2 — Marketplace
- Catégories et hiérarchie contrôlée.
- Catalogue de compétences et associations professionnelles.
- Services professionnels, cycle de publication/archivage et gestion des images.
- Recherche/découverte des professionnels, filtres, tri et pagination.
- Favoris et dashboards Client/Professionnel.

### Phase 3 — Transactions
- Demandes de service et cycle de vie.
- Devis/offres, versions et négociation.
- Commandes et snapshots des informations commerciales/adresse.
- Abstraction des paiements, transactions, idempotence et callbacks sécurisés.
- Portefeuille professionnel, retraits et commissions.
- Avis, conversations/messages et notifications transactionnelles.

### Phase 4 — Administration
- Dashboard et gestion des utilisateurs, rôles/permissions et professionnels.
- Vérification des professionnels.
- Gestion administrative des catégories, services, demandes, commandes et paiements.
- Signalements/modération, support et tickets.
- Centre d'audit et analytics.

### Phase 5 — Performance et exploitation
- Revue de la base et des index, stratégie de cache, queues et événements.
- Stockage public/privé des fichiers.
- Optimisations de requêtes API et document de revue finale.

### Phase 6 — Frontend web
- Design system et layouts par espace.
- Composants Blade partagés, CSS commun et JavaScript commun.
- Interfaces d'authentification et parcours d'inscription Client/Professionnel.
- Pages publiques et recherche.
- Espaces Client et Professionnel : profils, adresses, favoris, demandes, devis, commandes/paiements, revenus, portefeuille/retraits, avis, messages et notifications.
- Espace Admin : dashboard, utilisateurs, rôles, permissions, professionnels, catégories, services, demandes, commandes, paiements, signalements, vérification, support, audit et analytics.
- UX responsive, états de chargement/vide/erreur/succès et confirmations.

## Organisation et principes de maintenance

- Garder les contrôleurs, Form Requests, Resources, Policies, Services, modèles, migrations, vues, CSS, JS et tests séparés par responsabilité/fonctionnalité.
- Réutiliser les composants et layouts communs au lieu de dupliquer les mêmes éléments dans chaque écran.
- Ne pas déplacer ou renommer massivement les fichiers sans corriger tous les imports, routes, entrées Vite, tests et références.
- Préserver les migrations historiques et les invariants transactionnels; toute modification de schéma doit être rétrocompatible ou explicitement documentée.
- Ne jamais exposer les adresses précises, documents de vérification, secrets, tokens ou métadonnées internes dans les Resources publics.
- Ne pas déclarer une fonctionnalité prête uniquement parce que sa vue existe : vérifier le contrôleur, la route, l'autorisation, les données réelles et les états d'erreur.

## Branches et PR historiques

Le dépôt contient de nombreuses branches de fonctionnalités et de correction. Plusieurs PR ont déjà été fusionnées dans `phase-3/finalize`; les branches sources peuvent donc être en retard ou diverger sans que leurs changements soient absents du résultat consolidé. Les PR ouvertes doivent être comparées individuellement à cette branche avant toute action. Une différence de nom ou un nombre de commits en avance ne prouve pas qu'une fonctionnalité manque.

Ne pas fermer ni supprimer automatiquement les branches historiques : conserver leur traçabilité jusqu'à la revue finale.

## Porte de validation avant de déclarer la livraison prête

1. Vérifier que le build Vite réussit.
2. Exécuter toute la suite PHPUnit/Pest du projet.
3. Exécuter Pint sur les fichiers PHP concernés.
4. Vérifier migrations/factories/seeders sur une base de test propre.
5. Vérifier les autorisations et tests négatifs IDOR/BOLA sur chaque espace.
6. Vérifier les routes et liens des vues Blade, les entrées Vite et les formulaires CSRF.
7. Vérifier les parcours Client, Professionnel et Admin sur desktop et mobile.
8. Vérifier les invariants financiers, l'idempotence et les callbacks de paiement.
9. Vérifier le workflow GitHub Actions complet sur cette branche.
10. Documenter explicitement chaque contrôle réussi, échoué ou non exécuté.


## Résultats de l’audit de consolidation — 9 octobre 2026

### Vérification des branches

- Un contrôle structurel des arbres de fichiers des **105 branches** visibles du dépôt a été effectué. Les arbres ont été comparés à celui de la release; les anciens chemins propres aux premières branches de fondation ont été vérifiés comme variantes historiques, et leurs équivalents actuels (contrôleurs, Resources et tests) sont présents.
- Les modules fonctionnels des branches historiques sont représentés dans la release. Les différences relevées concernaient principalement des variantes historiques, des chemins renommés, des documents ou des tests remplacés par des tests plus récents.
- Les points de risque ont été comparés séparément au niveau des fichiers et du contenu : modèle/migration du profil professionnel, hiérarchie des catégories, idempotence des paiements, recherche publique, notifications, durcissement HTTP, cache, queues, stockage des fichiers et index de performance. Les variantes historiques de ces branches sont remplacées par des versions présentes dans la release; les tests plus récents du projet couvrent ces invariants.
- La release conserve la migration additive du profil professionnel et son backfill d’état, la configuration explicite du hachage des mots de passe, ainsi que les documents d’architecture et d’intégration utiles.
- Les tests couvrent notamment le schéma du profil professionnel et la rétention des journaux d’audit.

### Dernière validation automatisée réussie

Référence contrôlée : commit `122860d416050d16835bd4cdf8c603100be54d68` — [workflow CI #712](https://github.com/baraka-cmd/PROXIWORK/actions/runs/37857101057) — [PR #100](https://github.com/baraka-cmd/PROXIWORK/pull/100).

- Build frontend Vite : **réussi**.
- Validation stricte Composer : **réussie**.
- Suite Laravel : **382 tests réussis**, **1 368 assertions**.
- Laravel Pint : **réussi sur 490 fichiers PHP**.
- La suite signale encore **3 tests risqués** (avertissements PHPUnit, sans échec bloquant). Ils ne sont pas présentés comme des tests parfaitement propres.

Cette validation automatisée ne remplace pas une recette manuelle complète dans un navigateur ni un test avec de véritables fournisseurs de paiement Mobile Money. La PR vers `main` reste en brouillon; les limites non implémentées dans les branches historiques sont listées ci-dessous et ne doivent pas être annoncées comme des fonctionnalités prêtes pour la production.

## Fonctionnalités qui restent à compléter avant une mise en production réelle

Cet audit distingue les fonctionnalités effectivement intégrées de celles qui ne sont pas entièrement implémentées dans les branches historiques :

- **Réinitialisation du mot de passe côté navigateur :** l'API dispose des endpoints de demande et de réinitialisation, mais la route web `password.reset` est encore un placeholder JSON et aucune route `password.request` ni vue de formulaire n'est déclarée. Le parcours navigateur n'est pas complet.
- **Vérification d'e-mail côté navigateur :** les endpoints API existent, mais le contrôleur d'inscription web n'envoie pas de notification de vérification et les routes web ne proposent pas de parcours dédié.
- **OAuth Google :** le bouton de connexion est un emplacement préparé; aucune route OAuth Google fonctionnelle n'est définie.
- **Paiements de production :** le contrat et l'abstraction de paiement sont présents, mais le conteneur lie actuellement `PaymentGateway` à `FakePaymentGateway`. Les transactions Mobile Money réelles nécessitent un fournisseur, ses secrets de configuration et des tests de callbacks dans un environnement autorisé.

Ces éléments ne sont pas des fichiers oubliés lors d'une fusion : aucune des branches examinées ne contient une implémentation web complète de ces parcours ni un fournisseur de paiement réel. Ils restent des travaux distincts à planifier; la release ne doit pas être présentée comme prête pour une mise en production réelle tant qu'ils ne sont pas achevés et testés.

