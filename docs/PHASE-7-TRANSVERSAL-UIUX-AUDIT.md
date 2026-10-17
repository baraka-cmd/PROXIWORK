# Phase 7 — Audit transversal UI/UX (première tranche)

## Périmètre et état de référence

- Dépôt : `baraka-cmd/PROXIWORK`
- Branche de référence inspectée : `audit/current-project-baseline`
- Commit de départ : `880eebafd61cb98abffa7eca76fd21958e7a0c5f`
- Branche de travail : `feature/phase-7-transversal-uiux-audit`
- Objectif : améliorer la cohérence transversale sans modifier les règles métier, les autorisations, les routes, les contrats de données ou les calculs.

## Inventaire initial confirmé

Le dépôt contient notamment 124 vues Blade hors composants et courriels, avec des espaces distincts pour :
- public : accueil, recherche, catalogue de professionnels, profils et services ;
- authentification : connexion, inscription, vérification et réinitialisation du mot de passe ;
- client : tableau de bord, favoris, demandes, devis, commandes, paiements, profil, adresses, messages et notifications ;
- professionnel : profil, documents, services, demandes, devis, commandes, revenus, portefeuille, retraits, avis, messages et notifications ;
- administration : tableau de bord, utilisateurs, rôles/permissions, professionnels, catégories, services, demandes, commandes, paiements, retraits, analytics, audit, rapports et support ;
- vues transversales : layouts, composants, pages d'erreur et courriels.

Les routes web séparent les espaces avec les middlewares et contrôles de rôle existants, dont `auth`, `active.account`, `role:client` et les contrôles d'autorisation Blade. Ces mécanismes sont hors périmètre des modifications UI.

## Constats vérifiés dans le code

1. **Double chargement de Font Awesome** : `resources/views/layouts/app.blade.php` chargeait la feuille CSS distante Font Awesome 6.7.2 et la feuille locale `public/assets/fontawesome/css/all.min.css`. Le dépôt contient les assets locaux et les vues utilisent les classes Font Awesome. Le chargement distant et son `preconnect` ont été retirés ; le chemin local est conservé.
2. **Navigation masquée mais potentiellement tabulable** : `resources/js/common/app.js` utilisait `aria-hidden` pour signaler la fermeture des menus. Cet attribut seul ne retire pas les descendants de l'ordre de tabulation. Le code utilise désormais la propriété native `inert` en complément d'`aria-hidden`, et la rétablit à l'ouverture. Le comportement du menu desktop de l'espace de travail est préservé.
3. **Débordement horizontal global** : `resources/css/common/layouts.css` applique `overflow-x: hidden` à `body`. Ce choix peut masquer un débordement réel ; cependant, supprimer cette règle sans test navigateur sur l'ensemble des pages risquerait de révéler des régressions. La règle est donc documentée comme point de contrôle, pas modifiée dans cette tranche.
4. **Composants déjà disponibles** : `empty-state`, `loading`, `error-state`, `alert`, `success`, les composants de formulaire et `.table-responsive` existent. Aucune nouvelle variante redondante n'a été ajoutée dans cette tranche.

## Références de conception consultées

- Atlassian Design System — états vides : https://atlassian.design/components/empty-state
- GOV.UK Design System — résumé des erreurs : https://design-system.service.gov.uk/components/error-summary/
- GOV.UK Design System — messages d'erreur : https://design-system.service.gov.uk/components/error-message/
- W3C — WCAG 2.2 : https://www.w3.org/TR/WCAG22/

Principes retenus : états vides qui expliquent la situation et la suite possible, erreurs liées au champ et récupérables, focus clavier visible, et reflow à 320 CSS px. Ces références orientent l'audit ; elles ne signifient pas que toutes les pages ont déjà été testées contre chaque critère.

## Fichiers modifiés dans cette tranche

- `resources/views/layouts/app.blade.php` — suppression du doublon CDN, maintien de Font Awesome local.
- `resources/js/common/app.js` — gestion de `inert` pour les navigations mobiles masquées/ouvertes et distinction entre sidebar desktop et menu public mobile.
- `docs/PHASE-7-TRANSVERSAL-UIUX-AUDIT.md` — inventaire, constats et limites de validation.

## Vérifications et limites

- Inspection en lecture seule du commit de base, des routes web, layouts communs, composants d'interface, CSS/JS partagés, configuration Vite et inventaire des vues.
- La CI de la Phase 5 sur le commit de base est terminée avec succès : https://github.com/baraka-cmd/PROXIWORK/actions/runs/39042668817
- Cette CI concerne le commit de base, pas les changements de cette branche.
- À exécuter sur cette branche : build Vite, tests Laravel et vérifications de style prévues par la CI.
- Non vérifié ici : rendu visuel dans un navigateur réel, mesures de contraste, tests clavier/lecteur d'écran sur chaque vue, dimensions 320/360/390/768/1024/1440 px, zoom 200 %, Core Web Vitals et tests manuels de tous les parcours.
- Aucun contrôleur, service, modèle, route, policy, FormRequest, migration, calcul financier ou mécanisme métier n'est modifié dans cette tranche.
