# Phase 7 — Audit transversal UI/UX, accessibilité et régression

## Périmètre et état de référence

- Dépôt : `baraka-cmd/PROXIWORK`
- Branche de référence inspectée : `audit/current-project-baseline`
- Commit de départ : `880eebafd61cb98abffa7eca76fd21958e7a0c5f`
- Branche de travail : `feature/phase-7-transversal-uiux-audit`
- Objectif : améliorer la cohérence transversale sans modifier les règles métier, les autorisations, les routes, les contrats de données ou les calculs.

## Inventaire initial confirmé

Le dépôt contient 123 templates Blade hors vues de courriels : 96 vues de fonctionnalités et 27 fichiers partagés (17 composants Blade, 7 layouts, 2 pages d'erreur et `welcome.blade.php`). Les 96 vues fonctionnelles se répartissent ainsi : 9 publiques, 5 d'authentification, 23 client, 24 professionnelles et 35 administratives.
- public : accueil, recherche, catalogue de professionnels, profils et services ;
- authentification : connexion, inscription, vérification et réinitialisation du mot de passe ;
- client : tableau de bord, favoris, demandes, devis, commandes, paiements, profil, adresses, messages et notifications ;
- professionnel : profil, documents, services, demandes, devis, commandes, revenus, portefeuille, retraits, avis, messages et notifications ;
- administration : tableau de bord, utilisateurs, rôles/permissions, professionnels, catégories, services, demandes, commandes, paiements, retraits, analytics, audit, rapports et support ;
- vues transversales : layouts, composants, pages d'erreur et courriels.

Les routes web séparent les espaces avec les middlewares et contrôles de rôle existants, dont `auth`, `active.account`, `role:client` et les contrôles d'autorisation Blade. Ces mécanismes sont hors périmètre des modifications UI.

## Couverture de l'audit source-level

Les 96 vues fonctionnelles ont été parcourues avec un contrôle statique de structure (formulaires, boucles/branches d'absence de données, tableaux, wrappers responsive, images, messages et validations). Les composants partagés et layouts ont été examinés séparément. L'absence de `<x-empty-state>` n'est pas considérée automatiquement comme un défaut : plusieurs pages client et professionnel possèdent des états vides métier personnalisés via `isEmpty()` ou des conditions Blade. De même, les tables utilisent tantôt `.table-responsive`, tantôt `.table-wrap` ; il faut vérifier la règle CSS réellement appliquée avant de conclure à un défaut mobile.

Les listes financières examinées (commandes professionnelles, revenus, portefeuille, retraits, paiements client et listes d'administration) possèdent des wrappers de table dédiés. La présence d'un wrapper ne remplace toutefois pas un test sur appareil réel.

Cette inspection est un audit statique des templates, pas une validation visuelle complète : les états conditionnels ne peuvent pas tous être reproduits sans jeux de données et session navigateur.

## Constats vérifiés dans le code

1. **Double chargement de Font Awesome** : `resources/views/layouts/app.blade.php` chargeait la feuille CSS distante Font Awesome 6.7.2 et la feuille locale `public/assets/fontawesome/css/all.min.css`. Le dépôt contient les assets locaux et les vues utilisent les classes Font Awesome. Le chargement distant et son `preconnect` ont été retirés ; le chemin local est conservé.
2. **Navigation masquée mais potentiellement tabulable** : `resources/js/common/app.js` utilisait `aria-hidden` pour signaler la fermeture des menus. Cet attribut seul ne retire pas les descendants de l'ordre de tabulation. Le code utilise désormais la propriété native `inert` en complément d'`aria-hidden`, et la rétablit à l'ouverture. Le comportement du menu desktop de l'espace de travail est préservé.
3. **Débordement horizontal global** : `resources/css/common/layouts.css` applique `overflow-x: hidden` à `body`. Ce choix peut masquer un débordement réel ; cependant, supprimer cette règle sans test navigateur sur l'ensemble des pages risquerait de révéler des régressions. La règle est donc documentée comme point de contrôle, pas modifiée dans cette tranche.
4. **Annonces des alertes** : le composant `alert` utilisait une annonce assertive pour tous les types, y compris information et succès. Il choisit désormais `role` et `aria-live` de manière cohérente : assertif pour avertissement/erreur/danger, poli pour information/succès.
5. **Aide de formulaire masquée en cas d'erreur** : les composants `input`, `select` et `textarea` n'associaient que l'erreur OU l'aide au contrôle, et masquaient l'aide lorsque l'erreur était présente. Ils référencent désormais les deux éléments lorsqu'ils existent, afin de conserver les consignes utiles.
6. **Fermeture des alertes incohérente** : le JavaScript cherchait uniquement `[role="alert"]`, ce qui ne fonctionnait pas pour les alertes d'information/succès ni pour le composant de succès. La cible est maintenant la classe commune `.alert`.
7. **Comportement desktop de la navigation** : lors de la synchronisation après redimensionnement, les menus non-sidebar pouvaient rester `aria-hidden` et `inert` sur desktop. Le code rétablit désormais l'accès aux navigations visibles sur grand écran.
9. **Entrée JavaScript redondante** : `resources/js/app.js` importait `resources/js/pages/professional/services.js`, alors que les vues professionnelles de services chargent déjà ce script via leur propre entrée Vite. L'import global a été retiré pour éviter de réexécuter les gestionnaires sur les pages qui le chargent explicitement.
10. **Focus et mouvement réduit** : le contour de focus utilise désormais la couleur primaire pleine. Le défilement fluide est désactivé pour les personnes qui demandent une réduction des animations ; l'animation du squelette de chargement est également désactivée avec `prefers-reduced-motion`. Le contraste du focus doit toujours être mesuré sur les fonds réels.
11. **Composants déjà disponibles** : `empty-state`, `loading`, `error-state`, `alert`, `success`, les composants de formulaire et `.table-responsive` existent. Aucune nouvelle variante redondante n'a été ajoutée.

## Références de conception consultées

- Atlassian Design System — états vides : https://atlassian.design/components/empty-state
- GOV.UK Design System — résumé des erreurs : https://design-system.service.gov.uk/components/error-summary/
- GOV.UK Design System — messages d'erreur : https://design-system.service.gov.uk/components/error-message/
- W3C — WCAG 2.2 : https://www.w3.org/TR/WCAG22/

Principes retenus : états vides qui expliquent la situation et la suite possible, erreurs liées au champ et récupérables, focus clavier visible, et reflow à 320 CSS px. Ces références orientent l'audit ; elles ne signifient pas que toutes les pages ont déjà été testées contre chaque critère.

## Fichiers modifiés dans cette tranche

- `resources/views/layouts/app.blade.php` — suppression du doublon CDN, maintien de Font Awesome local.
- `resources/js/common/app.js` — gestion de `inert` pour la navigation responsive et fermeture cohérente des alertes.
- `resources/js/app.js` — retrait du script de services professionnels de l'entrée globale, car les vues concernées le chargent déjà comme entrée de page.
- `resources/css/common/reset.css` et `resources/css/common/ux-states.css` — contour de focus plus net et réduction du mouvement pour le défilement fluide et l'animation des skeletons.
- `resources/views/components/alert.blade.php` — niveaux d'annonce cohérents entre `role` et `aria-live`, y compris pour les types `error` et `danger`.
- `resources/views/components/input.blade.php` — association simultanée de l'aide et de l'erreur.
- `resources/views/components/select.blade.php` — association simultanée de l'aide et de l'erreur.
- `resources/views/components/textarea.blade.php` — association simultanée de l'aide et de l'erreur.
- `docs/PHASE-7-TRANSVERSAL-UIUX-AUDIT.md` — inventaire, constats et limites de validation.

## Vérifications et limites

- Inspection en lecture seule du commit de base, des routes web, layouts communs, composants d'interface, CSS/JS partagés, configuration Vite et inventaire des vues.
- La CI de la Phase 5 sur le commit de base est terminée avec succès : https://github.com/baraka-cmd/PROXIWORK/actions/runs/39042668817
- Cette CI concerne le commit de base, pas les changements de cette branche.
- La CI de la branche a réussi sur un commit précédent (tests Laravel et migrations MySQL) : https://github.com/baraka-cmd/PROXIWORK/actions/runs/39044686969. Les derniers correctifs ajoutés après ce run nécessitent une exécution CI à jour avant de conclure.
- Non vérifié ici : rendu visuel dans un navigateur réel, mesures de contraste, tests clavier/lecteur d'écran sur chaque vue, dimensions 320/360/390/768/1024/1440 px, zoom 200 %, Core Web Vitals et tests manuels de tous les parcours.
- Aucun contrôleur, service, modèle, route, policy, FormRequest, migration, calcul financier ou mécanisme métier n'est modifié dans cette tranche.
