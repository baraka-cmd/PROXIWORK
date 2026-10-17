# PROXIWORK — Thème clair et sombre

## Objectif

Fournir une préférence d'affichage cohérente sur les espaces public, client, professionnel, administration et authentification, sans toucher aux règles métier, aux autorisations ou aux données.

## Comportement

- Le thème est initialisé dans le document avant le chargement des styles afin de limiter le flash d'un thème incorrect.
- Au premier passage, PROXIWORK suit la préférence système de l'appareil.
- Un choix explicite est mémorisé dans `localStorage` sous la clé `proxiwork-theme` (`light` ou `dark`).
- Les boutons sont accessibles au clavier et leur libellé, leur icône et leur état `aria-pressed` reflètent le thème actif.
- Lorsque plusieurs onglets de PROXIWORK sont ouverts, un changement de préférence est répercuté dans les autres onglets.
- Si le stockage local est bloqué, le changement de thème fonctionne pour la page courante et la préférence système reste utilisable.

## Emplacements

- Navigation publique : composant Blade partagé `x-theme-toggle`.
- Barre supérieure commune aux espaces client, professionnel et administration : même composant.
- Écrans d'authentification : contrôle discret fixé en haut à droite.

## Architecture

- `resources/views/components/theme-toggle.blade.php` : bouton réutilisable et sémantique.
- `resources/views/layouts/app.blade.php` : initialisation précoce du thème.
- `resources/js/common/theme.js` : état, persistance, préférence système et synchronisation entre onglets.
- `resources/css/common/variables.css` : palette claire/sombre et couleurs sémantiques.
- `resources/css/common/theme.css` : styles du contrôle et harmonisation des surfaces communes.
- `resources/js/app.js` et `resources/css/app.css` : inclusion dans les bundles globaux existants.

## Vérifications à effectuer

1. Exécuter `npm run build` et la suite de tests Laravel/CI.
2. Vérifier la bascule clair/sombre sur accueil, recherche publique, connexion/inscription, tableau de bord client, professionnel et administration.
3. Recharger une page puis ouvrir une autre page : le thème choisi doit rester appliqué.
4. Ouvrir deux onglets : changer le thème dans l'un doit synchroniser l'autre.
5. Tester la préférence système lorsqu'aucun choix explicite n'est mémorisé.
6. Parcourir les formulaires, tableaux, menus, cartes, alertes, badges, états vides et modales dans les deux thèmes.
7. Tester clavier, focus visible, largeur mobile et zoom navigateur.

## Périmètre et limites de validation

Cette modification reste dans les vues de présentation, les feuilles de style et le JavaScript d'interface. Aucun contrôleur, service métier, route, modèle, policy, FormRequest, migration, paiement ou calcul financier n'est modifié.

Les tests automatisés et la compilation doivent être confirmés par la CI sur le commit final. Une validation visuelle complète nécessite encore de parcourir les écrans réels dans un navigateur ; le résultat de compilation seul ne prouve pas le contraste de chaque vue spécifique.
