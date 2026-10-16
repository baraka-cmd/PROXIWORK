# Phase 4 — Interfaces client PROXIWORK (C01–C05)

## Objectif

Moderniser l'expérience client en conservant les contrôleurs, services métier, routes, modèles, politiques d'autorisation et règles de visibilité existants. Cette phase est une intervention front-end uniquement.

## Références UX consultées

- Service Scheduling Client Dashboard UI/UX Design — https://www.behance.net/gallery/249024465/Service-Scheduling-Client-Dashboard-UIUX-Design
- Patterns de conception du Stripe Dashboard — https://stripe.kb.ryzedesk.com/articles/design
- Recommandations Stripe pour les vues de listes, cartes de statistiques et états vides — https://github.com/stripe/ai/blob/main/skills/stripe-apps/references/ui-extensions.md
- Étude de marketplace de services — https://appdesign.dev/en/app-development-app-marketplace-services-app-solutions/

Ces références servent d'inspiration pour la hiérarchie, la clarté des actions et les états d'interface. Elles ne sont pas copiées comme un thème complet.

## C01 — Dashboard client

Vue : `resources/views/client/dashboard.blade.php`

Styles : `resources/css/pages/client/dashboard.css`

Script : `resources/js/pages/client/dashboard.js`

- Hiérarchie visuelle séparant actions à suivre, indicateurs opérationnels et raccourcis personnels.
- Les actions ayant une priorité métier élevée sont visuellement distinguées sans créer de nouvelles règles de priorité.
- Les huit indicateurs et leurs routes existantes sont conservés.
- Le graphique des six mois utilise une échelle commune calculée à partir du maximum des valeurs affichées, au lieu d'une largeur arbitraire par volume.
- Les valeurs mensuelles exactes restent disponibles sous forme de liste textuelle accessible.
- Les statuts d'activité sont présentés à l'aide d'une correspondance explicite avec les valeurs des enums existants ; les valeurs inconnues ne sont pas rendues directement comme des chaînes techniques.
- Le libellé « Paiements à vérifier » décrit les paiements échoués, annulés ou expirés comptés par le service existant, sans changer le calcul.
- Le script ne marque plus chaque lien occupé au clic. La navigation native n'est pas présentée comme un état de chargement durable.

## C02 — Catalogue client des services

Vue : `resources/views/client/services/index.blade.php`

Styles : `resources/css/pages/client/services.css`

Script : `resources/js/pages/client/service-catalog.js`

- Filtres de recherche et de catégorie réorganisés avec une action de réinitialisation visible uniquement lorsque des critères sont actifs.
- Cartes homogènes avec image de couverture, catégorie, description, professionnel, durée, compétences, tarif et actions.
- Les quatre types de tarification existants (`fixed`, `range`, `from`, `quote`) ont des libellés de présentation distincts.
- L'ajout aux favoris réutilise `client.favorites.services.store`, la méthode POST et la protection CSRF existantes.
- Les erreurs de chargement d'image affichent un remplacement visuel.
- Les critères et la sélection de services restent côté serveur.

## C03 — Annuaire et profils professionnels

L'annuaire et les profils publics déjà présents sur la branche sont réutilisés, notamment :

- `resources/views/public/professionals/index.blade.php`
- `resources/views/public/partials/professional-card.blade.php`
- `resources/views/public/professionals/show.blade.php`
- `resources/css/pages/public/professionals.css`
- `resources/js/pages/public/professionals.js`

L'annuaire dispose déjà d'une photo servie par la route publique dédiée, d'un remplacement par initiales, des informations de réputation et d'un lien vers le profil. Le JavaScript existant gère aussi l'échec de chargement des photos. Cette phase conserve ce parcours et ne crée pas de seconde implémentation concurrente.

## C04 et C05 — Favoris professionnels et services

Vue : `resources/views/client/favorites/index.blade.php`

Styles : `resources/css/pages/client/favorites.css`

Script : `resources/js/pages/client/favorites.js`

- Une seule page conserve les favoris professionnels et services, les compteurs, onglets, recherche, tri, pagination et actions de retrait.
- Les cartes disponibles affichent les informations publiques et les actions déjà autorisées.
- Les photos de professionnels réutilisent la route publique de photo ; les images de services utilisent le stockage public existant.
- Les images indisponibles affichent un remplacement visuel.
- Si une cible devient indisponible, la carte n'affiche plus son nom, sa description, sa localisation, ses évaluations, ses images ni son tarif. Elle indique l'indisponibilité et permet toujours au client de retirer son propre favori.
- Les actions de retrait conservent la méthode DELETE, le jeton CSRF et la route existante.

## Assets

Les entrées suivantes sont déclarées dans `vite.config.js` :

- `resources/js/pages/client/service-catalog.js`
- `resources/js/pages/client/favorites.js`

## Audit backend — lecture seule

Fichiers examinés sans modification :

- `app/Http/Controllers/Web/Client/DashboardController.php`
- `app/Services/Client/Dashboard/ClientDashboardService.php`
- `app/Http/Controllers/Web/Client/ServiceCatalogController.php`
- `app/Http/Controllers/Web/Client/FavoriteController.php`
- `app/Services/FavoriteService.php`
- `routes/web.php`
- `app/Enums/ServiceRequestStatus.php`
- `app/Enums/OrderStatus.php`
- `app/Enums/PaymentStatus.php`
- `app/Enums/ServicePricingType.php`

Les règles serveur de visibilité, les calculs du dashboard, les critères de catalogue, les autorisations de favoris, les routes, les enums et la logique d'idempotence n'ont pas été changés dans cette phase.

## Validation et limites

Les fichiers ont été relus statiquement après écriture sur GitHub. Cela ne remplace pas l'exécution du build Vite, les tests Laravel/PHPUnit ou les vérifications visuelles et clavier dans un navigateur. Aucun résultat de build, de test ou de navigateur ne doit être considéré comme réussi tant qu'il n'a pas été réellement exécuté.

La branche de travail reste `feature/frontend-redesign`. Aucune fusion n'est effectuée par ce document.
