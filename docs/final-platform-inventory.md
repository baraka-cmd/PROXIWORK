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

**Statut de ce document :** inventaire de consolidation initial. Il ne remplace pas les résultats effectifs de CI ni une recette manuelle. La branche ne doit être annoncée comme validée qu'après examen des contrôles.

## Résultats de l'audit transversal des branches — 9 octobre 2026

### Couverture du dépôt

- Les arbres de fichiers des 103 branches listées au début de l'audit ont été comparés à la branche de release, avec examen ciblé des branches historiques de recherche, services, profils, administration, authentification, transactions et performance.
- Aucune fonctionnalité d'exécution unique provenant d'une ancienne branche de fonctionnalité n'a été identifiée comme absente de la release. Les différences restantes concernent surtout des versions antérieures de fichiers, des documents ou des tests historiques. Les tests historiques équivalents ont été comparés à la couverture actuelle avant de ne pas les reprendre.
- La migration de profil professionnel présente dans l'ancienne branche `feature/professional-profile-design` ne doit pas être fusionnée : la release contient déjà une migration ultérieure pour ces colonnes. Ajouter l'ancienne migration créerait des colonnes en double.
- Des tests supplémentaires ont été ajoutés sur la branche de contrôle pour la persistance des champs du profil, les en-têtes de sécurité des réponses publiques/privées et la rétention des journaux d'audit.

### Résultat CI de la branche de contrôle

Sur `release/proxiwork-quality-gate`, la dernière exécution complète de GitHub Actions a réussi : build frontend, validation Composer, tests et Pint sur les fichiers PHP de `app` et `tests`. La suite a rapporté **380 tests réussis, 1 364 assertions et 3 tests signalés « risky »**. Ces avertissements ne font pas échouer le job, mais restent à examiner; ils ne doivent pas être décrits comme des tests parfaitement propres.

### Limites confirmées — ne pas les présenter comme terminées

- **Réinitialisation du mot de passe côté navigateur :** l'API possède les endpoints de demande et de réinitialisation, mais la route web `password.reset` est encore un placeholder JSON et aucune route `password.request`/vue de formulaire n'est déclarée. Le parcours de réinitialisation depuis le navigateur n'est donc pas complet.
- **Vérification d'e-mail côté navigateur :** le modèle et les endpoints API prennent en charge la vérification, mais le contrôleur d'inscription web n'envoie pas la notification de vérification et les routes web ne proposent pas de parcours dédié. Le flux web ne doit pas être considéré comme vérifié.
- **OAuth Google :** le bouton de connexion est un emplacement préparé; aucune route OAuth Google fonctionnelle n'est définie dans cette release.
- **Paiements réels :** le contrat et l'abstraction de paiement sont présents, mais le conteneur lie actuellement `PaymentGateway` à `FakePaymentGateway`. Les paiements ne doivent pas être annoncés comme des transactions Mobile Money de production tant qu'un fournisseur réel et ses webhooks/configurations ne sont pas intégrés et testés.

Ces limites ne sont pas des fonctionnalités oubliées lors d'une fusion : elles ne sont pas implémentées complètement dans les branches examinées. Elles doivent rester explicitement listées comme travaux distincts avant de qualifier la plateforme de prête pour une mise en production réelle.

