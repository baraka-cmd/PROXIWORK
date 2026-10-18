# Mode clair et sombre — PROXIWORK

## Objectif

Fournir un choix de thème cohérent sur les pages publiques, l'authentification et les espaces client, professionnel et administration, sans changer le fonctionnement métier de la plateforme.

## Fonctionnement

- Le thème clair reste le choix par défaut afin de préserver l'apparence existante pour les utilisateurs qui n'ont pas encore choisi de thème.
- Le bouton partagé permet de passer du mode clair au mode sombre et inversement.
- Le choix est conservé localement dans le navigateur sous la clé `proxiwork-theme`; aucune donnée personnelle ni préférence de thème n'est envoyée au serveur.
- Le thème est appliqué dans l'en-tête HTML avant le chargement des feuilles de style afin de limiter le flash du mauvais thème au chargement.
- Les contrôles sont présents dans la navigation publique, la barre supérieure des espaces dashboard (client, professionnel, administration) et les pages d'authentification.
- Les couleurs partagées s'appuient sur les variables CSS existantes. Une couche de compatibilité couvre aussi les composants courants et plusieurs classes utilitaires historiques.
- Les boutons ont un nom accessible dynamique, une icône explicite et une zone tactile adaptée aux écrans mobiles.

## Périmètre technique

- Nouveau composant Blade partagé : `resources/views/components/theme-toggle.blade.php`.
- Nouveau contrôleur client du thème : `resources/js/common/theme.js`.
- Nouvelle feuille de style partagée : `resources/css/common/theme.css`.
- Entrées Vite et layouts mis à jour pour initialiser le thème et exposer le bouton.
- Aucun contrôleur, service, modèle, route, politique, FormRequest, calcul financier ou fichier de migration n'est modifié.

## Vérifications nécessaires

- Avant la fusion, vérifier que la CI est verte sur le dernier commit de la branche, notamment le build Vite, les tests Laravel et le job de migration MySQL sur base fraîche.
- Vérification visuelle manuelle sur desktop/mobile, zoom, contraste et parcours avec JavaScript désactivé non exécutée dans un navigateur durant cet audit.
- Les pages utilisant des couleurs codées en dur ou des styles en ligne peuvent nécessiter des ajustements spécifiques après revue visuelle. La couche globale ne remplace pas une validation de chaque page.
