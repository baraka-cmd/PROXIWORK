# Phase C13–C16 — Modernisation UI/UX du module Devis client

## Périmètre

Cette phase améliore les vues client existantes du module devis. Elle ne modifie ni les contrôleurs, ni les services métier, ni les FormRequests, ni les policies, ni les routes, ni les modèles, ni les migrations.

## Audit du backend effectué en lecture seule

Fichiers examinés sur `audit/current-project-baseline` :

- `routes/web.php` — routes GET de liste/détail et routes POST d'acceptation, de refus et de contre-proposition.
- `app/Http/Controllers/Web/Client/QuotationController.php` — autorisation, filtres, pagination, orchestration des actions.
- `app/Services/Quotation/QuotationService.php` — acceptation/refus, vérification du statut, de l'auteur de l'offre active, de l'expiration et de l'adresse avant création de commande.
- `app/Services/Quotation/QuotationNegotiationService.php` — verrouillage transactionnel, réponse de l'autre partie, devise conservée, création d'une nouvelle version et événement d'historique.
- `app/Services/Quotation/QuotationExpirationService.php` — expiration réelle des offres.
- `app/Http/Requests/Quotation/IndexClientQuotationsRequest.php` et `StoreQuotationOfferRequest.php` — validation serveur des filtres et des champs de proposition.
- `app/Policies/QuotationPolicy.php` — contrôle d'accès client/professionnel.
- `app/Enums/QuotationStatus.php`, `QuotationOfferActor.php` et `OrderStatus.php` — statuts et acteurs.
- `vite.config.js` et le JavaScript partagé du module client — conventions front-end existantes.

### Règles métier préservées

- Les routes et méthodes HTTP existantes sont conservées, ainsi que CSRF et les autorisations serveur.
- L'acceptation commerciale ne signifie pas que le paiement est effectué.
- La création de commande, l'adresse obligatoire, les statuts et l'expiration restent validés côté serveur.
- La contre-proposition conserve la devise de l'offre courante et crée une nouvelle version via le service métier.
- Les offres historiques et événements d'audit ne sont pas modifiés.
- Les montants affichés restent des données serveur ; le front-end ne détermine aucun prix faisant autorité.

## Modifications front-end

### C13 — Liste des devis

- Les devis dont l'offre professionnelle attend une décision client reçoivent une priorité visuelle supplémentaire et une indication textuelle « Votre décision ».
- Le bouton principal devient « Répondre au devis » uniquement lorsque la logique de la vue constate une offre valide du professionnel qui attend effectivement la réponse du client.
- Les échéances proches montrent la date limite exacte.
- Les filtres actifs sont visibles et peuvent être retirés individuellement. Les autres filtres validés sont conservés lors de cette opération.
- Les catégories statistiques actives exposent `aria-current="page"`.
- Les états vides existants et la pagination avec paramètres de recherche sont conservés.

### C14 — Détail, versions et historique

- L'état de commande est affiché avec des libellés français correspondant aux valeurs de l'enum existant.
- Les événements d'historique non reconnus utilisent un libellé neutre plutôt que d'exposer un identifiant technique brut.
- La version actuelle, les versions remplacées et la version acceptée restent distinctes.

### C15 — Décisions

- Les confirmations existantes d'acceptation/refus sont conservées.
- Les messages continuent de préciser que l'acceptation lance le flux de commande mais ne confirme pas le paiement.
- Les contrôles métier restent côté serveur ; la présentation ne crée aucune transition de statut.

### C16 — Contre-proposition

- Un résumé de l'offre actuelle est visible à proximité du formulaire pour comparer montant/devise, délai et validité.
- La devise reste affichée comme fixe et la page explique qu'aucune conversion automatique n'est appliquée.
- Les erreurs de validation disposent d'un résumé accessible, de liens vers les champs concernés, de messages associés aux contrôles et d'un focus sur le résumé après retour serveur.
- Les valeurs précédemment saisies restent réutilisées via `old()`.
- Le JavaScript de soumission et de confirmation existant est conservé ; le focus automatique respecte `prefers-reduced-motion`.

## Fichiers modifiés

- `resources/views/client/quotes/index.blade.php`
- `resources/views/client/quotes/show.blade.php`
- `resources/css/pages/client/favorites-requests-quotes.css`
- `resources/js/pages/client/favorites-requests-quotes.js`
- `docs/PHASE-C13-C16-CLIENT-QUOTES-UX.md`

Le CSS et le JavaScript sont partagés avec d'autres pages client : les nouvelles règles de style sont limitées aux classes de devis. Aucun nouveau paquet front-end n'est ajouté.

## Références UX consultées

- Sharetribe — workflow de négociation tarifaire pour marketplace : https://www.sharetribe.com/help/en/articles/12800532-how-regular-price-negotiation-transactions-work
- Sharetribe — parcours « Request a quote » et étapes de décision : https://www.sharetribe.com/changelog/request-a-quote/
- GOV.UK Design System — résumé des erreurs, focus clavier et liens vers les réponses invalides : https://design-system.service.gov.uk/components/error-summary/
- GOV.UK Design System — messages d'erreur liés aux champs et conservation des réponses : https://design-system.service.gov.uk/components/error-message/

Ces références ont servi de principes de conception, pas de templates à copier.

## Validation et limites

Les modifications ont été poussées sur la branche de travail `feature/client-quotes-ux`. Le diff doit être examiné et le workflow GitHub Actions doit terminer avant de confirmer l'état des tests. Aucune vérification visuelle dans un navigateur réel, aucun test manuel clavier/lecteur d'écran et aucune recette avec comptes client n'ont été exécutés dans le cadre de cette modification distante.

Aucun fichier métier backend n'a été modifié.
