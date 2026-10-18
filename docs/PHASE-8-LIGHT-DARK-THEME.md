# PROXIWORK — Mode clair et mode sombre

## Fonctionnalités intégrées

- Le choix clair/sombre est disponible dans l'en-tête public et dans la barre supérieure des espaces connectés : client, professionnel et administration.
- Le thème est appliqué au document entier par des variables CSS partagées pour harmoniser arrière-plans, surfaces, texte, bordures, formulaires, tableaux et états communs.
- Au premier accès, le thème suit la préférence système. Le choix manuel est conservé dans `localStorage` et prend priorité sur la préférence système.
- Le thème initial est appliqué avant le chargement des feuilles de style pour réduire le flash du mauvais thème.
- Les changements sont synchronisés entre les onglets ouverts du même navigateur.
- Le contrôle possède un nom accessible, un état `aria-pressed`, un focus visible et une version compacte sur mobile.
- Si le stockage local est désactivé, le changement reste effectif pour la page courante sans bloquer le reste du JavaScript.

## Fichiers concernés

- `resources/css/common/variables.css` — palette sémantique des deux thèmes.
- `resources/css/common/theme.css` — contrôle de thème et adaptations transversales.
- `resources/css/app.css` — chargement du fichier partagé de thème.
- `resources/views/components/theme-toggle.blade.php` — contrôle réutilisable.
- `resources/views/layouts/app.blade.php` — initialisation avant CSS.
- `resources/views/layouts/public.blade.php` et `resources/views/layouts/dashboard.blade.php` — emplacement du contrôle.
- `resources/js/common/app.js` — interaction, persistance et synchronisation.
- `tests/Feature/Frontend/SharedComponentsTest.php` — test de régression du composant.

## Vérifications

La CI doit confirmer la compilation Vite, les tests Laravel et les migrations MySQL à blanc. Cela ne remplace pas une inspection visuelle navigateur de toutes les pages et de tous les états conditionnels. Après récupération locale, vérifier au minimum l'accueil, la recherche, une page de profil/service, le tableau de bord client, une page financière professionnelle, l'administration, les formulaires et les tableaux dans les deux thèmes, à 320 px et sur desktop. Les éléments graphiques propres à certaines pages peuvent nécessiter des ajustements ciblés si une couleur est codée en dur dans une feuille CSS spécifique.
