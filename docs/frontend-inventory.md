# PROXIWORK — Inventaire initial du Front-End

- **Dépôt :** `baraka-cmd/PROXIWORK`
- **Branche de référence auditée :** `audit/current-project-baseline`
- **Commit de référence constaté :** `bc8a743c8696af868bc4c200c98e211d00cedaed`
- **Branche de travail Front-End :** `feature/frontend-redesign`
- **Phase 0 :** inventaire documentaire et cartographie initiale.
- **Phase 1 en cours :** fondations CSS globales, en commençant par les design tokens.
- **Portée actuelle :** changements applicatifs limités au Front-End ; aucun contrat métier, route ou contrôleur modifié.

## 1. Résumé de l'arborescence constatée

L'arbre Git de la branche de référence contient 6 963 entrées (arbre Git récursif, tous types confondus). Les répertoires Front-End comportent :

| Élément | Nombre constaté |
|---|---:|
| Vues Blade sous `resources/views` | 124 |
| Layouts Blade | 7 |
| Composants Blade dans `resources/views/components` | 17 |
| Fichiers CSS sous `resources/css` | 42 |
| Fichiers JavaScript sous `resources/js` | 26 |
| Fichier de routes Web | `routes/web.php` |
| Fichier de routes Admin | `routes/admin.php` |
| Fichier de routes API | `routes/api.php` |
| Configuration Vite | `vite.config.js` |
| Configuration npm | `package.json` |
| Workflow CI | `.github/workflows/ci.yml` |

Ces nombres décrivent les fichiers versionnés dans la référence auditée, pas un résultat d'exécution de l'application.

## 2. Arborescence des vues Blade

```text
resources/views/
├── admin/
│   ├── analytics/index.blade.php
│   ├── audit/{index,show}.blade.php
│   ├── auth/login.blade.php
│   ├── categories/{create,edit,index,show}.blade.php
│   ├── categories/partials/form.blade.php
│   ├── dashboard.blade.php
│   ├── orders/{index,show}.blade.php
│   ├── payments/{index,show}.blade.php
│   ├── permissions/{index,show}.blade.php
│   ├── professionals/{index,show}.blade.php
│   ├── rbac/index.blade.php
│   ├── reports/{index,show}.blade.php
│   ├── roles/{form,index,show}.blade.php
│   ├── service-requests/{index,show}.blade.php
│   ├── services/{index,show}.blade.php
│   ├── support/{index,show}.blade.php
│   ├── users/{index,show}.blade.php
│   ├── verification/index.blade.php
│   └── withdrawals/{index,show}.blade.php
├── auth/
│   ├── login.blade.php
│   ├── register.blade.php
│   ├── passwords/{email,reset}.blade.php
│   └── verify-email.blade.php
├── client/
│   ├── addresses/{form,index}.blade.php
│   ├── dashboard.blade.php
│   ├── favorites/index.blade.php
│   ├── messages/{index,show}.blade.php
│   ├── notifications/index.blade.php
│   ├── orders/{index,payment,show}.blade.php
│   ├── payments/{index,receipt,show}.blade.php
│   ├── profile/{edit,email-change-confirmation,show}.blade.php
│   ├── quotes/{index,show}.blade.php
│   ├── requests/{create,edit,index,show}.blade.php
│   └── services/index.blade.php
├── components/
│   ├── alert, avatar, badge, button, card, confirmation
│   ├── divider, empty-state, error-state, icon-button
│   ├── input, loading, select, skeleton, spinner
│   ├── success, textarea
│   └── (tous sont des composants Blade .blade.php)
├── emails/notifications/transactional.blade.php
├── errors/{4xx,5xx}.blade.php
├── layouts/
│   ├── admin.blade.php
│   ├── app.blade.php
│   ├── auth.blade.php
│   ├── client.blade.php
│   ├── dashboard.blade.php
│   ├── professional.blade.php
│   └── public.blade.php
├── professional/
│   ├── dashboard.blade.php
│   ├── documents/index.blade.php
│   ├── messages/{index,show}.blade.php
│   ├── notifications/index.blade.php
│   ├── orders/{index,show}.blade.php
│   ├── profile.blade.php
│   ├── profile-edit.blade.php
│   ├── quotes/{create,index,show}.blade.php
│   ├── requests/{index,show}.blade.php
│   ├── revenues/{index,show}.blade.php
│   ├── reviews/index.blade.php
│   ├── services/{form,index,preview}.blade.php
│   └── withdrawals/{create,index,show}.blade.php
├── public/
│   ├── home.blade.php
│   ├── home-dynamic.blade.php
│   ├── search.blade.php
│   ├── search-services.blade.php
│   ├── professionals/{index,show}.blade.php
│   ├── services/show.blade.php
│   └── partials/{professional-card,professional-filters}.blade.php
└── welcome.blade.php
```

## 3. Layouts et composants partagés

### Layouts observés

- `layouts.app` : document HTML racine, métadonnées, Font Awesome, chargement global Vite, messages de succès, contenu et pile de scripts.
- `layouts.public` : navigation publique.
- `layouts.auth` : enveloppe d'authentification.
- `layouts.dashboard` : structure de dashboard partagée.
- `layouts.client` : navigation et sections propres au client.
- `layouts.professional` : navigation et sections propres au professionnel.
- `layouts.admin` : navigation administrative.

### Composants Blade constatés

`alert`, `avatar`, `badge`, `button`, `card`, `confirmation`, `divider`, `empty-state`, `error-state`, `icon-button`, `input`, `loading`, `select`, `skeleton`, `spinner`, `success`, `textarea`.

Avant de créer un composant supplémentaire, il faudra vérifier si l'un de ces composants peut être étendu sans casser les usages actuels.

## 4. Styles CSS

### Commun

- `resources/css/app.css`
- `resources/css/common/variables.css`
- `resources/css/common/reset.css`
- `resources/css/common/typography.css`
- `resources/css/common/layouts.css`
- `resources/css/common/components.css`
- `resources/css/common/ux-states.css`
- `resources/css/common/confirmation.css`

Le fichier `app.css` importe ces styles communs ainsi que `pages/professional/requests-quotes.css`.

### Pages Admin

- `pages/admin/analytics.css`
- `pages/admin/dashboard.css`
- `pages/admin/orders.css`
- `pages/admin/payments.css`
- `pages/admin/permissions-professionals.css`
- `pages/admin/services-requests.css`
- `pages/admin/users-roles.css`
- `pages/admin/withdrawals.css`

### Pages Authentification

- `pages/auth/login.css`
- `pages/auth/register.css`

### Pages Client

- `pages/client/addresses.css`
- `pages/client/dashboard.css`
- `pages/client/favorites-requests-quotes.css`
- `pages/client/favorites.css`
- `pages/client/messages.css`
- `pages/client/notifications.css`
- `pages/client/orders-payments.css`
- `pages/client/profile.css`
- `pages/client/service-requests.css`
- `pages/client/services.css`

### Pages Professionnel

- `pages/professional/dashboard.css`
- `pages/professional/notifications.css`
- `pages/professional/orders-revenues.css`
- `pages/professional/profile.css`
- `pages/professional/quotes.css`
- `pages/professional/requests-quotes.css`
- `pages/professional/requests.css`
- `pages/professional/revenues.css`
- `pages/professional/reviews-messages.css`
- `pages/professional/services.css`
- `pages/professional/wallet-withdrawals.css`

### Pages publiques

- `pages/public/home.css`
- `pages/public/professionals.css`
- `pages/public/search.css`

## 5. JavaScript

### Points d'entrée et utilitaires communs

- `resources/js/app.js`
- `resources/js/common/app.js`
- `resources/js/common/confirmation.js`

`resources/js/app.js` importe les deux scripts communs et `pages/professional/services.js`.

### Admin

- `pages/admin/analytics.js`
- `pages/admin/dashboard.js`
- `pages/admin/permissions-professionals.js`
- `pages/admin/services-requests.js`
- `pages/admin/users-roles.js`

### Authentification

- `pages/auth/login.js`
- `pages/auth/register.js`

### Client

- `pages/client/addresses.js`
- `pages/client/dashboard.js`
- `pages/client/favorites-requests-quotes.js`
- `pages/client/messages.js`
- `pages/client/orders-payments.js`
- `pages/client/profile.js`

### Professionnel

- `pages/professional/dashboard.js`
- `pages/professional/notifications.js`
- `pages/professional/orders-revenues.js`
- `pages/professional/revenues.js`
- `pages/professional/reviews-messages.js`
- `pages/professional/services.js`
- `pages/professional/wallet-withdrawals.js`

### Public

- `pages/public/home.js`
- `pages/public/professionals.js`
- `pages/public/search.js`

## 6. Familles de routes Web identifiées

Le fichier `routes/web.php` définit les familles suivantes :

- **Public :** accueil, recherche, détail d'un service, annuaire et profil professionnel, image de réalisation.
- **Authentification :** inscription, connexion, déconnexion, récupération/réinitialisation du mot de passe, vérification d'e-mail.
- **Client :** dashboard, catalogue de services, favoris, demandes et pièces jointes, devis et contre-offres, commandes, planning et livraison, paiements/reçus/remboursements, profil/photo/mot de passe/changement d'e-mail, adresses, notifications/préférences, conversations/messages et avis.
- **Professionnel :** dashboard, demandes, devis, profil et compétences, documents, services et réalisations, commandes/planning/livraison, revenus/export/détail, portefeuille, retraits et support lié aux retraits, avis/réponses, conversations/messages, notifications/préférences.
- **Admin :** les routes admin sont réparties entre `routes/web.php` (notamment dashboard/RBAC/retraits/auth) et `routes/admin.php` (utilisateurs, rôles/permissions, professionnels/vérification, catégories, services/réalisations, demandes, commandes, paiements, support, audit, analytics, rapports/modération).

Les noms et URL complets doivent être consultés dans les fichiers de routes, car les préfixes et noms peuvent être hérités des groupes. Cette note ne remplace pas l'analyse détaillée route → contrôleur → vue.

## 7. Configuration des assets et premier point à vérifier

- `package.json` déclare les scripts `npm run build` et `npm run dev`, Vite, le plugin Laravel, Tailwind CSS v4 et Chart.js.
- `vite.config.js` déclare 56 entrées de ressources.
- Le layout racine charge `resources/css/app.css` et `resources/js/app.js`, sauf en environnement de test.
- Les pages utilisent aussi des appels `@vite` spécifiques via la pile `head`.

### Point à vérifier avant toute refonte

Les vues `resources/views/admin/withdrawals/index.blade.php` et `show.blade.php` appellent directement :

```php
@vite(['resources/css/pages/admin/withdrawals.css'])
```

Or `resources/css/pages/admin/withdrawals.css` n'apparaît pas dans la liste des entrées de `vite.config.js` et n'est pas importé par `resources/css/app.css` dans l'état audité. Cela peut provoquer une entrée absente du manifeste de production. Il faudra confirmer le résultat par le build CI et corriger ce point sur la branche de travail si nécessaire.

Les fichiers communs non listés comme entrées individuelles ne sont pas automatiquement un problème : plusieurs sont importés par `app.css` ou `app.js`. Toute correction sera basée sur la chaîne d'import réelle, pas uniquement sur une comparaison de noms.

## 8. Tests et CI repérés

- Workflow : `.github/workflows/ci.yml`.
- Job Laravel : installation Node/PHP, build Vite, validation Composer, suite PHPUnit et contrôle Pint ciblé sur les fichiers PHP modifiés.
- Job MySQL : migrations fraîches, seeders et tests financiers/retraits sous MySQL.
- Les tests sont organisés sous `tests/Feature`, `tests/Integration`, `tests/Support` et `tests/Unit`.
- Des tests Front-End et de parcours Web existent notamment pour les composants partagés, l'authentification, les pages publiques, client, professionnel, retraits et administration.

L'existence d'un workflow ne prouve pas qu'il a réussi sur le commit audité. Le statut réel du commit et les résultats CI doivent être consultés séparément avant de déclarer le projet validé.

## 9. Principes de la refonte

1. Le code métier et les contrats des vues sont préservés : routes, méthodes HTTP, CSRF, validation, variables Blade, autorisations et statuts.
2. Les modifications se limitent au Front-End, sauf autorisation explicite distincte.
3. Les composants communs sont améliorés en évitant les doublons.
4. Chaque interface est reliée à une référence Figma pertinente, puis adaptée aux données réelles de PROXIWORK.
5. Chaque groupe de changements est contrôlé par diff, build/tests disponibles et revue de PR.
6. Aucune fusion dans la branche de référence ne se fera sans autorisation explicite.

## 10. Clôture de l'inventaire initial et suite

La cartographie initiale est suffisante pour démarrer les fondations communes sans toucher aux contrats métier. Elle reste un document vivant : les associations route → contrôleur → vue → layout → CSS/JS seront précisées au fil de l'audit des pages et de la vérification des assets réellement chargés. Les changements applicatifs peuvent maintenant commencer dans la branche dédiée, avec un périmètre front-end contrôlé, un diff relu et des validations CI.


## 11. Matrice initiale contrôleur Web → vue

Cette matrice a été vérifiée dans les contrôleurs Web présents sur la branche de référence. Elle indique les vues rendues explicitement par les méthodes consultées ; les actions qui redirigent ou ne rendent pas de page HTML sont omises.

### Public et authentification

| Contrôleur | Vue rendue |
|---|---|
| `HomePageController` | `public.home-dynamic` |
| `PublicSearchController` | `public.search-services`, `public.services.show`, `public.professionals.index`, `public.professionals.show` |
| `LoginController` | `auth.login` |
| `RegisterController` | `auth.register` |
| `PasswordResetLinkController` | `auth.passwords.email` |
| `NewPasswordController` | `auth.passwords.reset` |
| `EmailVerificationController` | `auth.verify-email` |

### Client

| Contrôleur | Vues rendues |
|---|---|
| `Client DashboardController` | `client.dashboard` |
| `ServiceCatalogController` | `client.services.index` |
| `FavoriteController` | `client.favorites.index` |
| `ServiceRequestController` | `client.requests.index`, `client.requests.create`, `client.requests.show`, `client.requests.edit` |
| `QuotationController` | `client.quotes.index`, `client.quotes.show` |
| `OrderController` | `client.orders.index`, `client.orders.show`, `client.orders.payment` |
| `PaymentController` | `client.payments.index`, `client.payments.show`, `client.payments.receipt` |
| `ProfileController` | `client.profile.show`, `client.profile.edit`, `client.profile.email-change-confirmation` |
| `AddressController` | `client.addresses.index`, `client.addresses.form` |
| `NotificationController` | `client.notifications.index` |
| `MessageController` | `client.messages.index`, `client.messages.show` |
| `ReviewController` | Actions de soumission/signalement, redirection vers l'écran d'origine |

### Professionnel

| Contrôleur | Vues rendues |
|---|---|
| `Professional DashboardController` | `professional.dashboard` |
| `DocumentController` | `professional.documents.index` |
| `ProfileController` | `professional.profile`, `professional.profile-edit`, `professional.services.index`, `professional.services.form`, `professional.services.preview` |
| `ServiceRequestController` | `professional.requests.index`, `professional.requests.show` |
| `QuotationController` | `professional.quotes.index`, `professional.quotes.show`, `professional.quotes.create` |
| `OrderController` | `professional.orders.index`, `professional.orders.show` |
| `RevenueController` | `professional.revenues.index`, `professional.revenues.show` |
| `WalletController` | `professional.wallet.index` |
| `WithdrawalController` | `professional.withdrawals.index`, `professional.withdrawals.create`, `professional.withdrawals.show` |
| `ReviewController` | `professional.reviews.index` |
| `MessageController` | `professional.messages.index`, `professional.messages.show` |
| `NotificationController` | `professional.notifications.index` |

### Administration

| Contrôleur | Vues rendues |
|---|---|
| `AdminAuthController` | `admin.auth.login` |
| `Admin DashboardController` | `admin.dashboard` |
| `AnalyticsController` | `admin.analytics.index` |
| `AuditController` | `admin.audit.index`, `admin.audit.show` |
| `CategoryController` | `admin.categories.index`, `admin.categories.create`, `admin.categories.show`, `admin.categories.edit` |
| `OrderController` | `admin.orders.index`, `admin.orders.show` |
| `PaymentController` | `admin.payments.index`, `admin.payments.show` |
| `PermissionController` | `admin.permissions.index`, `admin.permissions.show` |
| `ProfessionalController` | `admin.professionals.index`, `admin.professionals.show` |
| `ReportController` | `admin.reports.index`, `admin.reports.show` |
| `RoleController` | `admin.roles.index`, `admin.roles.form`, `admin.roles.show` |
| `ServiceController` | `admin.services.index`, `admin.services.show` |
| `ServiceRequestController` | `admin.service-requests.index`, `admin.service-requests.show` |
| `SupportController` | `admin.support.index`, `admin.support.show` |
| `UserController` | `admin.users.index`, `admin.users.show` |
| `VerificationController` | `admin.verification.index`, réutilise `admin.professionals.show` pour le détail |
| `WithdrawalController` | `admin.withdrawals.index`, `admin.withdrawals.show` |
| `RbacDashboardController` | `admin.rbac.index` |

Les contrôleurs de téléchargement de fichiers/réalisations et les actions de formulaire peuvent retourner des fichiers, des réponses HTTP ou des redirections plutôt qu'une vue.

## 12. Vérifications complémentaires à effectuer avant la première refonte

Ces points sont des constats d'audit à confirmer, pas des corrections déjà effectuées :

1. **Entrée Vite des retraits admin :** les deux vues de retrait demandent directement `resources/css/pages/admin/withdrawals.css`, mais cette ressource ne figure pas dans les entrées Vite et n'est pas importée par `resources/css/app.css`. Confirmer par le build de production/CI.
2. **Navigation publique Services :** `layouts.public` conditionne le lien Services à l'existence de `public.services.index`. Dans les routes Web examinées, le détail public `public.services.show` existe, mais aucune route `public.services.index` n'a été trouvée. Vérifier si le lien est volontairement masqué ou si la navigation doit pointer vers une route existante.
3. **Vues publiques candidates à un usage ancien :** `public.home.blade.php` et `public.search.blade.php` sont présentes dans l'arborescence, alors que le contrôleur consulté rend respectivement `public.home-dynamic` et `public.search-services`. Vérifier les usages via includes, tests et routes avant de les déclarer obsolètes ; ne rien supprimer sur la seule base du nom.
4. **Correspondance CSS/JS par page :** les feuilles et scripts sont partiellement regroupés par domaine (ex. commandes/paiements, demandes/devis, avis/messages). La cartographie doit suivre les appels `@vite`, `@push('head')`, `@push('scripts')`, `@stack` et les imports réels, plutôt que supposer une relation 1:1 entre chaque vue et un fichier.
5. **Tests et CI :** le workflow déclenché par le commit documentaire est encore en cours au moment du premier contrôle. Son résultat final sera vérifié séparément.

## 13. État d'avancement de cette phase

- [x] Branche de travail créée à partir du commit exact de `audit/current-project-baseline`.
- [x] Inventaire des dossiers, vues, layouts, composants, CSS et JavaScript.
- [x] Première cartographie contrôleurs Web → vues.
- [x] Lecture de la configuration Vite, de `package.json` et du workflow CI.
- [ ] Cartographie finale route nommée → contrôleur/action → vue → layout → ressources CSS/JS.
- [ ] Vérification du statut CI du commit de documentation.
- [ ] Revue des éventuelles vues/ressources orphelines sans les supprimer automatiquement.

Aucune route, aucun contrôleur, modèle, migration ou fichier applicatif n'a été modifié dans cette phase.


## 12. Phase 1 — Fondations CSS : design tokens

**Première livraison effectuée sur la branche de travail :** `resources/css/common/variables.css`.

Le fichier conserve les noms de variables déjà utilisés par les feuilles CSS existantes et étend le système avec des tokens sémantiques regroupés par responsabilité :

- **Couleurs de marque et d'interaction :** primaire, hover, active, teintes douces et bordures de marque.
- **Surfaces et contenu :** fond de page, surface, surface élevée, surface au survol, bordures, textes principaux/secondaires/inversés, liens et overlay.
- **États métier :** succès, avertissement, erreur et information, chacun avec teinte douce et bordure.
- **Typographie :** famille Poppins avec fallbacks système, tailles jusqu'au display fluide, graisses, interlignes et approche typographique.
- **Espacements :** échelle régulière basée sur un rythme de 4 px, en conservant les variables historiques.
- **Formes et élévation :** rayons, ombres progressives, ombre de focus et ombre de survol de carte.
- **Mise en page :** largeurs de conteneurs et de lecture, largeur de sidebar, hauteur de topbar et hauteurs de contrôles.
- **Mouvement :** durées, courbes d'accélération et transitions sémantiques.
- **Superposition :** échelle de z-index documentée pour les en-têtes, sidebars, overlays, menus, modales, toasts et tooltips.
- **Thème sombre explicite :** valeurs de couleurs dédiées lorsque l'application applique `data-theme="dark"`; aucun changement automatique du thème utilisateur n'est imposé.
- **Accessibilité du mouvement :** les tokens de transition sont neutralisés lorsque le système demande une réduction des animations.

### Garanties de compatibilité

- Aucun contrôleur, route, nom de variable Blade, endpoint ou contrat métier n'a été modifié.
- Aucun nouveau framework ni dépendance npm n'a été ajouté.
- Les anciens tokens `--color-primary`, `--space-*`, `--transition-*`, `--radius-*` et autres restent disponibles.
- Les points d'entrée Vite ne sont pas modifiés dans cette livraison.
- Les règles spécifiques aux pages ne sont pas remplacées par des styles génériques.

### Validation

- Le commit initial des tokens est `37b15d27d3cac3a82365e4830ffd54a2bcfebc6e`. Un correctif complémentaire (`06fbf9f6a873ab264874df48054a1e6616d94eb8`) applique aussi la préférence de réduction de mouvement aux tokens de durée.
- Le workflow GitHub Actions du commit initial est consultable ici : https://github.com/baraka-cmd/PROXIWORK/actions/runs/38734145918. Le résultat du commit le plus récent doit être vérifié séparément.
- Le statut doit être considéré comme **en cours** tant que GitHub Actions n'a pas publié de conclusion. Le succès des commits documentaires précédents ne constitue pas une validation de ce commit CSS.

### Suite de Phase 1

1. Vérifier le résultat CI du commit CSS.
2. Examiner le reset et la typographie pour repérer les conflits de cascade, de focus et de couleurs avant de les modifier.
3. Consolider les primitives partagées (boutons, champs, cartes, états) en réutilisant les tokens, sans réécrire les composants à l'aveugle.
4. Auditer le JavaScript commun pour les interactions clavier, menu responsive, fermeture Escape, focus et nettoyage des événements.
5. Construire ensuite les composants et pages par familles, en vérifiant chaque diff et le comportement existant.


## 14. Phase 2 — Structures communes et navigation responsive

**Livraison en cours sur `feature/frontend-redesign` : layouts partagés, sidebar, header et pied de page.**

### Sidebar des espaces client, professionnel et administration

- Sur grand écran, la navigation est compacte par défaut : rail d'icônes, avec largeur réservée au contenu.
- Au survol ou au focus clavier, la sidebar s'élargit avec une transition courte et affiche les libellés, les sections et les actions de compte.
- Les liens actifs conservent leur état visuel ; le compteur de notifications client reste visible dans le rail compact.
- La sidebar développée se superpose au contenu au lieu de déplacer brutalement la page.
- Sur tablette et mobile, le comportement hors-canvas existant est conservé : bouton hamburger, panneau latéral, bouton de fermeture et arrière-plan assombri.
- Le JavaScript commun synchronise désormais l'état du voile mobile avec l'état réel du bouton et son attribut accessible `aria-hidden`.

### Header partagé

- Hiérarchie renforcée entre l'espace courant, le titre et les actions de page.
- Espacements et taille des contrôles ajustés pour les largeurs intermédiaires et mobiles.
- Identité du compte présentée dans une surface discrète, avec réduction de l'information secondaire sur petit écran.
- Les actions propres à chaque page restent pilotées par le slot Blade existant : aucune fonctionnalité ou route fictive n'est ajoutée.

### Pied de page public

- Réorganisation en trois zones : identité et description, liens de navigation réellement disponibles, mentions de copyright.
- Les liens sont conditionnés aux routes Laravel existantes ; le pied de page s'empile proprement sur mobile/tablette.
- Aucune route métier ni logique de contrôleur n'a été modifiée.

### Fichiers touchés

- `resources/css/common/layouts.css` : rail d'icônes extensible, transitions, header et pied de page responsives.
- `resources/views/layouts/public.blade.php` : structure du pied de page et navigation conditionnelle.
- `resources/js/common/app.js` : synchronisation du voile de la sidebar mobile et de son état accessible.

### Contrôles et limites

- Le workflow CI a été déclenché pour le commit `45cee40d8003be7dab36fb20775382a2ecd06c00` ; au moment de cette mise à jour, il était encore en attente. Suivre le résultat réel ici : https://github.com/baraka-cmd/PROXIWORK/actions/runs/38735832646.
- Les deux contrôles CI de la fondation reset/typographie ont terminé avec succès : https://github.com/baraka-cmd/PROXIWORK/actions/runs/38734764897 et https://github.com/baraka-cmd/PROXIWORK/actions/runs/38734769970.
- La validation visuelle dans un navigateur à plusieurs largeurs reste à faire ; le succès CI ne remplace pas ce contrôle.
- Aucun merge ou pull request n'a été effectué.

### Suite

1. Attendre et examiner le résultat CI du dernier commit.
2. Contrôler les règles de navigation et les interactions clavier dans le script commun, sans réécrire les comportements déjà corrects.
3. Poursuivre la consolidation des primitives partagées, puis vérifier les pages par familles avec les routes, vues et assets existants.

## 15. Phase 3 — Primitives UI partagées (livraison initiale)

Cette phase prépare une base cohérente pour les boutons, champs, cartes, badges, alertes, tableaux, pagination et dialogues de confirmation. Elle reste volontairement progressive : les composants existants sont conservés, et les écrans seront migrés/contrôlés par familles au lieu d'une réécriture massive.

### Boutons, champs, cartes et badges

- États de focus clavier visibles et cohérents, états hover/active/disabled conservés.
- États de chargement, champs en lecture seule et messages d'erreur rendus plus lisibles.
- Cartes interactives : indication visuelle aussi lorsque le focus clavier se trouve dans la carte.
- Badges : meilleure résistance aux libellés longs et aux petits écrans.
- Respect renforcé de `prefers-reduced-motion` : les animations de spinners ne tournent plus pour les utilisateurs qui demandent une réduction des animations.

### Tableaux et défilement horizontal

- Ajout d'une primitive `.table-responsive` avec surface, bordure, coins arrondis, scrollbar visible et interaction tactile.
- Adaptation des conteneurs existants `.table-wrap`, `.admin-table-wrap` et `.finance-table-wrap` pour que les tableaux trop larges défilent à l'intérieur de leur propre zone plutôt que de provoquer un débordement de la page.
- Largeur minimale contrôlée sur les tableaux de données pour préserver la lisibilité des colonnes ; la zone reste défilable horizontalement sur mobile.
- Ajout de styles réutilisables de pagination (`.pagination`, `.pagination-list`) et d'une vue de pagination Laravel personnalisée dans `resources/views/vendor/pagination/tailwind.blade.php` : liens précédent/suivant, numéros, ellipses, état de page courante, résumé du nombre de résultats et libellés accessibles. Les paginateurs `simplePaginate()` et les éventuels composants de pagination entièrement spécifiques aux pages doivent encore être repérés et vérifiés individuellement.

### Dialogues de confirmation

- Entrée visuelle discrète du fond et du dialogue, avec variante de type feuille sur petits écrans.
- Contrastes et focus clavier renforcés ; les animations sont désactivées si `prefers-reduced-motion` est actif.
- Le composant Blade et son contrôleur JavaScript existants sont conservés ; pas de nouvelle logique métier.

### Fichiers modifiés

- `resources/css/common/components.css`
- `resources/css/common/ux-states.css`
- `resources/css/common/confirmation.css`

### Validation restante

- Les modifications CSS sont soumises au workflow CI de la branche ; consulter le résultat le plus récent avant de considérer la livraison validée.
- La CI valide compilation/tests, mais ne démontre pas à elle seule le rendu visuel de chaque vue. Le contrôle multi-écran réel et l'harmonisation des paginations propres aux différentes familles de pages restent nécessaires.
- Aucun contrôleur, route, modèle, calcul métier ou règle d'autorisation n'a été modifié dans cette phase.
