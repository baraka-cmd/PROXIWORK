# Mode clair / mode sombre — PROXIWORK

## État de départ

L'application possédait déjà un début de palette sombre dans `resources/css/common/variables.css` via `[data-theme='dark']`, mais il n'existait pas de commande d'interface ni de logique JavaScript pour sélectionner, mémoriser et appliquer ce choix à l'ensemble des pages. La palette seule ne constituait donc pas une fonctionnalité utilisateur complète.

## Mise en place

- `resources/js/common/theme.js` : bascule claire/sombre, synchronisation des commandes présentes sur la page, mémorisation du choix dans `localStorage`, prise en compte du thème système tant que l'utilisateur n'a pas fait de choix explicite, et gestion du stockage indisponible.
- `resources/views/layouts/app.blade.php` : application du thème enregistré avant le chargement des styles pour éviter le flash clair au démarrage.
- `resources/views/components/theme-toggle.blade.php` : commande réutilisable, libellé accessible, état `aria-pressed`, icône et intitulé synchronisés.
- `resources/views/layouts/public.blade.php` : commande dans la navigation publique.
- `resources/views/layouts/dashboard.blade.php` : commande dans la barre supérieure commune aux espaces client, professionnel et administration.
- `resources/views/layouts/auth.blade.php` : commande disponible sur les pages d'authentification.
- `resources/css/common/variables.css` : tokens sémantiques pour les surfaces, textes, bordures, couleurs d'état et ombres.
- `resources/css/common/theme.css` : règles partagées pour les composants, formulaires, tableaux, alertes, navigation et contrôle du thème en sombre.
- `resources/css/app.css` et `resources/js/app.js` : chargement des modules communs.
- `tests/Feature/Frontend/SharedComponentsTest.php` : test de rendu du composant et de ses attributs d'accessibilité.

## Comportement attendu

1. Au premier accès, PROXIWORK suit le thème du système d'exploitation.
2. Le bouton permet de passer du clair au sombre ou inversement.
3. Le choix explicite est mémorisé dans le navigateur et conservé entre les pages et les sessions sur ce même navigateur.
4. Le thème est appliqué aux pages publiques, à l'authentification, aux espaces client/professionnel et à l'administration.
5. Le changement est purement visuel : il ne modifie ni les données, ni les rôles, ni les permissions, ni les règles métier.

## Validation et limites

- Le composant a un test Feature Laravel dédié.
- La validation de compilation JavaScript/CSS et l'ensemble des tests Laravel doivent être confirmés par la CI sur le commit exact de cette branche avant fusion.
- La vérification visuelle exhaustive dans un navigateur, le contrôle des contrastes WCAG sur chaque page et les parcours clavier/lecteur d'écran ne sont pas revendiqués comme réalisés dans cet audit distant. Ils restent nécessaires avant de déclarer une conformité WCAG complète.
- Les pages qui contiennent des couleurs d'arrière-plan codées en dur dans leurs feuilles de style spécifiques peuvent nécessiter des adaptations ciblées supplémentaires ; les tokens et styles partagés sont désormais thématisés, mais ce document ne prétend pas remplacer un audit visuel de chaque état et de chaque page.

## Périmètre protégé

Aucune route, autorisation, logique métier, contrôleur, service, modèle, migration, contrat de données ou calcul financier n'est modifié.
