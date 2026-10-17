# Mode clair et mode sombre — PROXIWORK

## Comportement
- Un contrôle partagé est disponible dans la navigation publique et la barre supérieure des espaces client, professionnel et administration.
- Le choix explicite « clair » ou « sombre » est conservé dans le navigateur sous la clé `proxiwork-theme`.
- En l'absence de choix enregistré, le thème suit la préférence système (`prefers-color-scheme`) et se met à jour si celle-ci change.
- Le thème initial est appliqué dans le document avant les feuilles de style afin de limiter le flash de thème.
- Le contrôle expose son état avec `aria-pressed`, un libellé accessible explicite et une icône décorative.

## Architecture et périmètre
- Le thème s'appuie sur les variables CSS existantes et ajoute des couleurs sémantiques adaptées au fond sombre.
- Le script est chargé par l'entrée JavaScript globale Vite.
- Le changement est purement visuel côté navigateur : aucun endpoint, rôle, permission, donnée, calcul financier ou règle serveur n'est modifié.

## Vérifications à effectuer
- Exécuter le build Vite et la suite de tests Laravel dans CI.
- Vérifier en navigateur réel les pages publiques, client, professionnel et administration, notamment les formulaires, tableaux, graphiques et composants utilisant des couleurs codées en dur.
