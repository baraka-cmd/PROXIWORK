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

### Méthode et périmètre

- Les arbres de fichiers des **105 branches visibles** du dépôt ont été comparés récursivement à celui de cette release. Cette comparaison structurelle sert à repérer les fichiers présents dans une branche mais absents de la livraison; elle ne remplace pas une revue ligne par ligne de chaque ancienne implémentation.
- Les chemins uniques relevés dans les branches historiques ont été examinés. Les anciens contrôleurs/Resources rangés dans des sous-dossiers de la Phase 1 sont remplacés par les contrôleurs/Resources actuels et leurs tests. L’ancienne migration complète du profil professionnel est remplacée par une création minimale, des migrations additives et un test de schéma. Les tests de recherche historiques sont remplacés par une suite plus complète, complétée pendant cet audit.
- Les PR ouvertes historiques pertinentes (#6, #11, #18, #20, #24, #25 et #38) ont été comparées à la release. Elles ne doivent pas être fusionnées aveuglément : leurs implémentations sont plus anciennes, et les fonctionnalités correspondantes sont représentées dans la branche consolidée. La PR #100 reste une PR de revue en brouillon, non fusionnée dans `main`.
- Le workflow de bootstrap historique `.github/workflows/bootstrap-laravel.yml` reste volontairement exclu : il ne représente pas une fonctionnalité métier et la branche de livraison dispose déjà de son workflow CI.

### Corrections et couvertures ajoutées pendant l’audit

- **Réinitialisation du mot de passe web :** le lien de la page de connexion n’était pas opérationnel et la route de réinitialisation renvoyait du JSON de démonstration. La release comprend désormais les routes, contrôleurs et formulaires Blade de demande et de changement du mot de passe, avec le broker Laravel, validation du mot de passe, limitation des requêtes et réponse générique pour ne pas révéler si un e-mail est enregistré.
- **Profil professionnel :** la migration additive `2026_10_09_000001_add_professional_profile_details_and_visibility.php` ajoute les champs de description, expérience, prix, devise, localisation, rayon de service, visibilité, statut et vérification qui ne figuraient pas dans la migration de création minimale.
- **Tests supplémentaires :** parcours web de réinitialisation, recherche par titre de service publié, exclusion des compétences archivées et catégories inactives, prix de type fourchette, protection des champs sensibles du profil, contrôle d’accès et isolation des préférences de notification, et assertions d’inscription sur les préférences et le token.
- **Style PHP :** le contrôle Pint avait signalé un espacement dans `tests/Feature/Messaging/MessagingApiTest.php`; ce point a été corrigé et un workflow CI ultérieur a réussi avant les dernières additions de tests.

### État de validation

- Un workflow CI sur le commit `802d07fa6c0c15349c43f05bab83f2b0df44aa25` a réussi : build Vite, validation Composer, suite Laravel (**397 tests réussis, 1 438 assertions**) et contrôle Pint. Il signalait encore 3 tests risqués.
- Les tests et vérifications ajoutés après ce commit déclenchent de nouveaux workflows. **Le dernier commit doit être validé par un workflow vert avant de déclarer cette release prête.** Ne pas considérer les résultats d’un commit antérieur comme la validation des modifications plus récentes.
- La CI ne prouve pas à elle seule la livraison réelle des e-mails, l’intégration d’un fournisseur de paiement en production, le parcours navigateur de bout en bout sur tous les appareils, ni le déploiement sur la machine locale. Ces contrôles restent à effectuer dans l’environnement cible.

### Décision de livraison

Ne pas supprimer l’ancienne copie locale et ne pas fusionner la PR #100 dans `main` avant que le workflow du dernier commit soit vert et que la revue finale des différences soit terminée. La branche de release reste l’artefact à récupérer pour les essais locaux, mais sa validation finale dépend du résultat CI le plus récent.

## Livraison finale candidate — 9 octobre 2026

La branche `release/proxiwork-final-delivery` est créée à partir de `release/proxiwork-full-platform` pour isoler la livraison candidate sans toucher à `main` ni aux branches historiques.

Le rapport complémentaire `docs/final-platform-audit.md` consigne la méthode, les références comparées, les tests de sécurité présents, le résultat CI du commit source et les limites à ne pas présenter comme des intégrations de production.

Le commit source `f095926ce233a9c2e6c0f21113b4c2e1fad2cac5` a un workflow CI réussi (run 758, ID `37896656382`), comprenant le build Vite, Composer, les tests Laravel et Pint. Ce résultat est une preuve pour le commit source uniquement. La livraison candidate doit être considérée validée seulement après un résultat vert du workflow exécuté sur son dernier commit.

La revue a confirmé que les tests `tests/Feature/Security/SecurityHeadersTest.php` et `tests/Feature/Audit/AuditRetentionTest.php` existent déjà dans l'arbre de release, ainsi que l'ajout global du middleware `AddSecurityHeaders` dans `bootstrap/app.php`. Les branches de hardening plus anciennes ne doivent donc pas être fusionnées en bloc : elles divergent de la release et certaines modifications pertinentes sont déjà présentes.

Les limites de production (notamment fournisseur de paiement réel, OAuth Google si retenu, livraison réelle des e-mails et recette navigateur/local) restent documentées dans le rapport d'audit.

