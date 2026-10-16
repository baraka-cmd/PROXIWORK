# P12 — UI/UX communication et réputation côté client (C39–C45)

## Objectif

Moderniser les interfaces clientes existantes de notifications, conversations, messages, avis et signalements sans reconstruire les fonctionnalités métier ni modifier les routes, contrôleurs, services, règles de validation, schémas ou autorisations.

## Audit du backend — lecture seule

Référence examinée : `audit/current-project-baseline`.

- **Notifications (C39–C40)** : `NotificationController` valide les filtres `status`, `category`, `q` et `archived`; les opérations de lecture, archivage, restauration et préférences passent par des routes POST/PATCH. Les notifications sont recherchées via la relation de l’utilisateur connecté. `safeInternalUrl()` prépare une destination interne sûre. La vue ne doit pas remplacer cette destination par une URL issue du navigateur ni écrire sur simple GET.
- **Conversations (C41–C43)** : `MessageController` et `ConversationService` gèrent la liste et le fil. Les messages sont soumis à la validation serveur, avec corps jusqu’à 5 000 caractères, maximum cinq pièces jointes, 10 Mo par fichier et les formats configurés par `StoreMessageRequest`. L’envoi conserve la clé d’idempotence, CSRF et les routes de téléchargement autorisées. Les pièces jointes ne deviennent jamais publiques.
- **Avis (C44)** : `ReviewController`, `StoreReviewRequest` et `ReviewService` restent responsables du rôle, de l’appartenance de la commande, de son état terminé, de la note de 1 à 5 et de l’unicité de l’avis. Le commentaire est facultatif et limité à 2 000 caractères.
- **Signalement de réponse (C45)** : la route existante exige une commande visible par le client, une réponse existante et publiée, puis transmet le motif et la description à `ModerationService`. Un signalement ne supprime pas la réponse.
- **Pas de page de détail de notification ajoutée** : les actions C40 restent intégrées à la liste existante.

Aucune modification de logique métier ou de schéma de données n’est prévue dans cette PR.

## Changements d’interface

### C39–C40 — Notifications et actions

- Sous-titre plus utile et repères de navigation entre boîte de réception et archives.
- Libellés de catégories en français plutôt que des identifiants techniques.
- Distinction textuelle « Non lue » / « Lue » en plus du traitement visuel.
- Résumé des filtres actifs, états vides mieux adaptés à la recherche et aux archives, et réinitialisation qui conserve le contexte d’archive.
- Icônes de compte/sécurité adaptées aux événements de profil et de sécurité.
- Préférences e-mail, SMS et push conservent les avertissements honnêtes sur les canaux non configurés.
- Les actions continuent d’utiliser les routes POST/PATCH et les destinations sûres fournies par le serveur.

### C41 — Liste des conversations

- État non lu et état épinglé plus faciles à repérer.
- Contexte de commande/demande mieux identifié.
- Filtres actifs visibles, réinitialisation qui conserve la boîte de réception ou les archives, et messages vides distincts des recherches sans résultat.
- Libellé du compteur explicite : conversations affichées sur la page courante, sans prétendre compter tout l’historique.

### C42–C43 — Fil et composeur

- État de la conversation visible (active, bloquée ou fermée) et actions secondaires plus compréhensibles.
- La région de synchronisation n’annonce plus chaque mise à jour périodique comme une alerte. Les nouveaux messages reçus ont une annonce dédiée.
- Garde anti-chevauchement pour éviter les requêtes de synchronisation concurrentes et arrêt du polling après expiration de session.
- Le fil ne saute plus systématiquement en bas quand de nouveaux messages arrivent : il suit le bas uniquement si l’utilisateur y était déjà; sinon un contrôle permet de revenir aux messages récents.
- Prévisualisation des pièces jointes sélectionnées avec nom, taille et possibilité de retirer chaque fichier avant l’envoi.
- Vérification de confort côté navigateur sur le nombre, la taille et l’extension des fichiers; le serveur reste la source d’autorité.
- Libellés explicites et limites d’entrée visibles. Les messages et noms de fichiers insérés dynamiquement restent construits avec des nœuds DOM et `textContent`, sans injection `innerHTML`.
- CSRF, idempotence, contrôles d’accès et téléchargements protégés préservés.

### C44–C45 — Avis et signalement

- Erreurs liées aux champs concernés et repérables par les technologies d’assistance.
- Le motif et la description du signalement sont restaurés après une erreur de validation; le formulaire s’ouvre de nouveau si l’un de ces champs est en erreur.
- Explication claire du rôle de la modération : transmission d’un signalement n’équivaut pas à la suppression automatique de la réponse.
- Limite de 5 000 caractères indiquée pour la description du signalement et de 2 000 caractères pour le commentaire d’avis.
- La confirmation de publication de l’avis et la confirmation de transmission du signalement restent déclenchées par le flux existant; aucun état métier n’est simulé côté navigateur.

## Direction visuelle

Réutilisation des classes, composants et tokens existants : surfaces claires, bordures discrètes, palette existante et accent principal PROXIWORK. Les ajouts CSS sont ciblés sur les pages concernées et les interactions clavier; les feuilles partagées ne sont pas réinitialisées globalement.

## Références UX et accessibilité consultées

- SaaSUI.Design, *SaaS Notification Center UI: Real Screenshots & UX Patterns (2026)* — hiérarchie lu/non lu, triage, préférences et états vides : https://www.saasui.design/blog/saas-notification-center-ux-patterns
- SaaSUI.Design, *SaaS Chat & Messaging UX: Examples & Patterns (2026)* — fil, composeur, états d’envoi et accusés de lecture : https://www.saasui.design/blog/saas-chat-messaging-ux-patterns
- SaaSUI.Design, *SaaS Email & Inbox UX: Real Examples & Patterns (2026)* — filtres, aperçu et parcours de réponse : https://www.saasui.design/blog/saas-email-inbox-ux-patterns
- GitHub Docs, *Managing notifications from your inbox* — filtres, séparation lu/non lu, triage et actions groupées : https://docs.github.com/en/subscriptions-and-notifications/how-tos/viewing-and-triaging-notifications/managing-notifications-from-your-inbox
- Slack Help, *View all your unread messages* — parcours de rattrapage des non-lus, filtres et accès aux messages récents : https://slack.com/help/articles/226410907-View-all-your-unread-messages
- Intercom Help, *The Inbox explained* — vues de conversations organisées par filtres et contexte de traitement : https://www.intercom.com/help/en/articles/6258745-the-inbox-explained
- W3C WAI, technique ARIA22 — annoncer les changements d’état via `role="status"` sans déplacer le focus : https://www.w3.org/WAI/WCAG21/Techniques/aria/ARIA22
- W3C WAI, WCAG 2.1 — navigation clavier, identification des erreurs et messages d’état : https://www.w3.org/TR/WCAG21/

Ces références guident les principes d’interface; elles ne sont pas copiées comme identité graphique.

## Fichiers concernés

- `resources/views/client/notifications/index.blade.php`
- `resources/css/pages/client/notifications.css`
- `resources/views/client/messages/index.blade.php`
- `resources/views/client/messages/show.blade.php`
- `resources/css/pages/client/messages.css`
- `resources/js/pages/client/messages.js`
- `resources/views/client/orders/show.blade.php`
- `resources/css/pages/client/orders-payments.css`

## Vérifications et limites

- Les contrôleurs, services, FormRequests, enums, routes, modèles et migrations n’ont pas été modifiés.
- Les tests de navigateur, clavier, lecteur d’écran, rendu mobile et parcours de fichiers doivent être vérifiés sur l’application exécutée.
- Le build Vite et les tests Laravel doivent être vérifiés via les workflows GitHub après ouverture de la PR. Aucun résultat ne doit être annoncé avant que les workflows correspondants soient terminés.
