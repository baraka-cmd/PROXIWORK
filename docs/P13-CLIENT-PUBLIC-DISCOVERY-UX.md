# P13 — Découverte publique et annuaire client

## Objectif

Compléter le parcours d'acquisition côté client : accueil public, recherche de services, annuaire des professionnels et profil public. Cette mise à jour complète la consolidation C01–C45 de la PR #150, sans reconstruire les pages métier ni modifier les contrats backend.

## Audit backend en lecture seule

Routes et données existantes examinées sur `audit/current-project-baseline` :

- `public.search` et `public.services.show` pour la recherche et le détail public d'une prestation.
- `public.professionals.index`, `public.professionals.show` et `public.professionals.photo` pour l'annuaire, le profil public et la photo.
- `PublicSearchController` et `ProfessionalSearchService` pour les filtres, la visibilité, les relations chargées et les données effectivement disponibles dans les vues.

Règles conservées :

- Le backend continue de décider quels services et professionnels sont publics et visibles.
- Les filtres sont soumis aux FormRequests existantes ; aucune recherche métier n'est déplacée dans le navigateur.
- Les photos restent servies par la route publique qui applique les contrôles de visibilité existants.
- Les liens vers la demande, la messagerie et les favoris utilisent les routes et protections CSRF déjà prévues.
- Le profil affiche au plus les six avis publiés les plus récents déjà chargés par le service existant. La vue ne lance pas de nouvelle requête de réalisations et ne dépend pas de nouvelles variables de contrôleur.
- Aucun contrôleur, service, route, FormRequest, modèle, policy, migration, table ou contrat API n'est modifié.

## Changements UI

- Accueil public : cohérence accrue avec les tokens de couleur et respect dynamique de la préférence de réduction des animations.
- Recherche de services : hiérarchie, filtres, résultats, suggestions et états de recherche améliorés.
- Annuaire : cartes professionnelles plus lisibles, fallback de photo, informations de confiance et filtres mieux guidés.
- Profil public : présentation éditoriale, compétences, services, informations utiles et avis existants mieux structurés.
- Composants publics partagés : pied de page harmonisé et messages d'erreur de filtres plus explicites.
- Le fichier `professionals.css` importe explicitement `search.css`, nécessaire aux styles de l'annuaire ; cette dépendance évite de perdre les styles de grille et de cartes.

## Validation et limites

- Le diff de la PR doit rester limité aux vues, CSS, JavaScript et documentation front-end.
- La compilation Vite, les tests Laravel, Pint et les migrations MySQL doivent être confirmés par le workflow déclenché sur le dernier commit de la PR #150.
- Les essais navigateur desktop/tablette/mobile, clavier, lecteur d'écran et les parcours avec un compte client restent à réaliser sur une instance exécutée. Une CI verte ne prouve pas ces contrôles manuels.
