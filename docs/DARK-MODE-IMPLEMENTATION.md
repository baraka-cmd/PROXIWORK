# PROXIWORK — Thème clair et sombre

## Fonctionnement livré dans cette branche

- Un contrôle réutilisable `<x-theme-toggle />` est placé dans la navigation publique et dans la barre supérieure commune des espaces connectés (client, professionnel et administration).
- Le choix clair/sombre est conservé dans le navigateur avec la clé `proxiwork-theme-preference`.
- Lorsqu'aucun choix n'a été enregistré, le thème initial suit les préférences du système. La préférence système reste suivie jusqu'au premier choix manuel.
- Le thème est appliqué sur l'élément `html` avant le chargement des feuilles de style pour limiter le flash clair/sombre.
- Le contrôle est utilisable au clavier, possède un nom accessible, reflète son état avec `aria-pressed` et met à jour son icône/son libellé.
- Les changements faits dans un autre onglet sont synchronisés via l'événement `storage`.
- Les couleurs partagées sont définies à l'aide des variables CSS existantes ; les couleurs de statut, surfaces, champs, tableaux et navigations disposent d'adaptations pour le thème sombre.
- Aucun changement de route, règle métier, contrôleur, service, modèle, autorisation ou contrat de données.

## Limites de validation

Cette branche doit encore passer les workflows CI du dépôt. Une validation visuelle réelle des pages publiques, client et professionnel, des graphiques, états vides, formulaires, tableaux, modales et menus à plusieurs largeurs d'écran est nécessaire pour confirmer les éléments qui utilisent des couleurs codées en dur dans des feuilles spécifiques. Ne pas déclarer la conformité WCAG AA sans mesure de contraste et tests clavier/lecteur d'écran.
