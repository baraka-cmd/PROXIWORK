# Phase 2 — Interfaces publiques PROXIWORK

## Périmètre consolidé

Cette phase modernise les interfaces publiques existantes sur la branche `feature/frontend-redesign`, sans créer de deuxième parcours métier et sans modifier les contrôleurs, services métier, modèles ou routes pour les besoins visuels.

| Point | Route existante | Vue | Périmètre livré |
|---|---|---|---|
| P01 — Accueil | `GET /` (`home`) | `resources/views/public/home.blade.php` | Hero et recherche vers le catalogue réel, accès professionnels/inscription, parcours client et professionnel, contenus dynamiques issus du contrôleur d’accueil, sections de confiance et CTA. |
| P02 — Recherche publique | `GET /search` (`public.search`) | `resources/views/public/search-services.blade.php` | Recherche GET, filtres existants, tri/pagination, suggestions et historique facultatifs, états vides et conservation des critères. La page recherche des services ; elle n’est pas l’annuaire des professionnels. |
| P03 — Annuaire | `GET /professionals` (`public.professionals.index`) | `resources/views/public/professionals/index.blade.php` | Cartes, filtres, localisation/réputation et accès aux profils, pagination, état vide. |
| P04 — Profil professionnel | `GET /professionals/{professional}` (`public.professionals.show`) | `resources/views/public/professionals/show.blade.php` | Identité, photo avec repli sur initiales, informations publiques, services, compétences/langues, avis paginés, réalisations admissibles et actions de contact/favoris adaptées au rôle. |
| P05 — Détail service | `GET /services/{service:slug}` (`public.services.show`) | `resources/views/public/services/show.blade.php` | Présentation, images du service, description et périmètre, tarif/durée, professionnel associé, réalisations admissibles, favoris et demande via les routes existantes. |
| P06 — Réalisations | `GET /service-realizations/{realization}/image` (`public.service-realizations.image`) | Galeries intégrées aux vues P04 et P05 ; aucun écran autonome constaté | Images servies par l’endpoint existant soumis aux règles de visibilité ; pas de nouvelle route de galerie ni de page inventée. Les images indisponibles sont retirées proprement de la galerie du profil, avec mise à jour du compteur et masquage de la section lorsqu’elle devient vide. |

## Contrats et garde-fous conservés

- Les noms de routes existants sont réutilisés ; aucune URL de navigation ou action métier n’est inventée.
- La recherche et la visibilité des services/professionnels restent déterminées côté serveur.
- Les actions de demande, contact et favoris continuent d’utiliser les routes et protections déjà présentes (dont CSRF pour les formulaires POST).
- Les prix sont affichés à partir des données du service ; le navigateur ne calcule ni ne fixe les montants métier.
- Les réalisations ne deviennent pas publiques simplement parce qu’une URL d’image est présente : le contrôleur existant vérifie approbation, participation confirmée, disponibilité publique du service et type MIME autorisé.
- Les photos de profil restent servies par l’endpoint public existant, qui vérifie lui aussi la visibilité et le type MIME.
- Les vues utilisent Blade échappé, les tokens CSS existants, les entrées Vite dédiées et des adaptations responsive/focus clavier.
- Aucun schéma, contrôleur, service métier, modèle ou fichier de routes n’a été modifié pour cette consolidation finale de Phase 2.

## Assets de référence

- Accueil : `resources/css/pages/public/home.css`, `resources/js/pages/public/home.js`
- Recherche : `resources/css/pages/public/search.css`, `resources/js/pages/public/search.js`
- Annuaire/profil : `resources/css/pages/public/professionals.css`, `resources/js/pages/public/professionals.js`
- Détail service : `resources/css/pages/public/service-detail.css`, `resources/js/pages/public/service-detail.js`
- Entrées Vite : `vite.config.js`
- Layout partagé : `resources/views/layouts/public.blade.php`

## État de validation et critères de clôture

- Revue statique des routes, vues, assets Vite et points de visibilité effectuée dans le dépôt GitHub.
- Contrôle statique de la présence des états vides, de la pagination et des liens/actions existants effectué lors des passes précédentes.
- **Non confirmé dans cette exécution :** compilation Vite, suite complète PHPUnit, tests navigateur, rendu visuel réel sur mobile/tablette/desktop, clavier/lecteur d’écran et résultats CI sur le dernier commit.
- Aucun statut de CI n’est déclaré réussi sans résultat observable. La phase ne doit être considérée comme validée en production qu’après ces contrôles.
- Les changements restent sur la branche `feature/frontend-redesign`. La branche de référence `audit/current-project-baseline` reste intacte ; aucune fusion ou PR n’est effectuée dans ce commit.

