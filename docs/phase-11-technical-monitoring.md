# Phase 11 — Supervision technique

## État de l'implémentation

La phase ajoute un centre de supervision intégré à l'administration PROXIWORK. Il réutilise les composants métier existants et n'expose ni secrets, ni payloads de jobs, ni contenus de notifications, ni traces d'exception.

### 77. Tâches asynchrones
- Volumes par file : total, attente et tâches réservées.
- Ancienneté de la tâche en attente la plus ancienne.
- Heartbeat émis depuis la boucle des workers toutes les 20 secondes ; un worker est considéré observé actif pendant deux minutes.
- Les heartbeats anciens sont purgés après sept jours.

### 78. Tâches échouées
- Total des enregistrements `failed_jobs` et dix dernières références.
- Affichage limité au nom de classe, à la file, à l'UUID et à la date.
- Aucun payload ou stack trace n'est exposé.
- Aucun retry générique n'est proposé : il faut démontrer l'idempotence de chaque job avant d'autoriser une reprise.

### 79. Notifications
- Agrégation des livraisons par statut.
- Historique des tentatives échouées/inconnues, avec canal, code d'erreur et date.
- Aucun destinataire ni contenu de message n'est exposé.
- L'historique détaillé existant reste la source métier de consultation.

### 80. Paiements
- Agrégation par fournisseur et statut.
- Détection des paiements restés initiés/en attente/en traitement depuis plus de 15 minutes.
- Nombre de transactions inconnues et dix derniers événements de paiement expurgés.
- Aucun appel réel aux fournisseurs n'est déclenché depuis le tableau de bord ; leur connectivité et la réconciliation externe restent à connecter selon les API des fournisseurs.
- Aucune modification manuelle de statut ou de solde n'est permise.

### 81. Stockage et fichiers
- Accès et capacité des disques locaux/publics lorsque le système d'exploitation permet de les mesurer.
- Commande `php artisan storage:integrity-check`, planifiée chaque semaine.
- Vérification des références de fichiers connues : médiathèque CMS, images de services, documents professionnels, pièces jointes de messages, demandes et commandes.
- Seuls les compteurs par table sont conservés ; les chemins ne sont pas affichés.
- Aucun fichier n'est supprimé. Les fichiers orphelins non référencés ne sont pas encore inventoriés.

### 82. Erreurs applicatives
- Enregistrement des exceptions reportées dans `application_incidents`.
- Regroupement par classe d'exception et emplacement technique (fichier/ligne).
- Conservation du nombre d'occurrences et des dates de première/dernière apparition.
- Messages, traces, URL complètes, données de requête et identifiants personnels exclus du registre.
- Les journaux bruts ne sont pas consultables depuis l'interface Admin.

### 83. Performance et disponibilité
- Middleware de mesure HTTP et agrégation par minute : volume, réponses 5xx, temps moyen et histogramme de latence.
- Le P95 présenté est une classe approximative de l'histogramme, pas un percentile exact.
- Les mesures sont archivées par le scheduler et conservées 30 jours.
- Contrôle ponctuel de la base de données.
- La disponibilité externe de bout en bout nécessite un moniteur indépendant de l'hébergement PROXIWORK ; le endpoint Laravel `/up` ne suffit pas à lui seul.

## Sécurité et exploitation
- Accès au centre via la permission RBAC `admin.monitoring.view`.
- Les opérations sensibles restent hors du tableau de bord tant que leurs garanties métier ne sont pas démontrées.
- Les erreurs de collecte ne doivent jamais interrompre les workers ni les réponses HTTP.
- Rétention : métriques HTTP 30 jours, heartbeats 7 jours, incidents 90 jours, historiques de contrôle de fichiers 90 jours.
- Vérifier le scheduler avec `php artisan schedule:list`.
- Maintenir les workers par un gestionnaire de processus et vérifier que le scheduler Laravel s'exécute régulièrement.

## Limites à traiter avec l'infrastructure réelle
1. Ajouter un fournisseur externe de disponibilité/alerting (indépendant du serveur applicatif).
2. Ajouter les contrôles de connectivité et la réconciliation auprès des fournisseurs de paiement, après confirmation de leurs API et mécanismes d'authenticité.
3. Définir des procédures de reprise autorisées job par job, sur preuve d'idempotence.
4. Ajouter, si nécessaire, un inventaire contrôlé des fichiers orphelins ; aucune suppression automatique n'est prévue.
