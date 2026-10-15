# PROXIWORK — Inventaire initial du Front-End

- **Dépôt :** `baraka-cmd/PROXIWORK`
- **Branche de référence auditée :** `audit/current-project-baseline`
- **Commit de référence constaté :** `bc8a743c8696af868bc4c200c98e211d00cedaed`
- **Branche de travail Front-End :** `feature/frontend-redesign`
- **Portée de cette étape :** inventaire documentaire uniquement. Aucun fichier applicatif n'a été modifié.

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

## 10. Prochaine étape

Compléter la matrice détaillée route → contrôleur → vue → layout → CSS/JS et vérifier les assets réellement chargés par chaque page. Aucun fichier applicatif ne doit être modifié tant que cette cartographie n'est pas suffisamment établie.
