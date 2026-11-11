# Phase 11 — Supervision technique

## Implémentation initiale des points 77 à 83

Cette livraison ajoute une première vue de supervision intégrée à l'administration PROXIWORK. Elle réutilise les tables et services déjà présents : queue Laravel en base, `failed_jobs`, `notification_deliveries`, `payments`, `payment_transactions`, stockage Laravel et journaux applicatifs.

### Données effectivement observées

- Files database queue : volumes en attente et réservés, ancienneté de la tâche en attente la plus ancienne.
- Workers : heartbeat émis depuis la boucle réelle de chaque worker, limité à une écriture toutes les 20 secondes et considéré actif pendant deux minutes.
- Performance HTTP : comptage agrégé des requêtes, réponses 5xx, temps moyen et classe P95 par histogramme, avec archivage à la minute via le scheduler.
- Jobs échoués : dix dernières références, nom de classe réduit, file et date d'échec. Payload et stack trace non exposés.
- Notifications : volumes par statut, avec lien vers l'historique détaillé déjà existant.
- Paiements : agrégation par fournisseur et statut, transactions inconnues.
- Stockage local/public : accès au disque, capacité système lorsqu'elle est disponible et dernier contrôle d'intégrité des références fichiers connues (commande planifiée quotidienne).
- Journaux : présence, taille et date de dernière écriture du fichier de log, sans exposition de son contenu.
- Incidents applicatifs : regroupement par classe d'exception et emplacement technique, avec compteur et dates ; messages et traces exclus.
- Performance HTTP : comptage agrégé par minute, taux 5xx, temps moyen et classe P95 approximative via histogramme.
- Incidents applicatifs : regroupement par classe d'exception et point technique (fichier/ligne), avec compteur et dates de première/dernière occurrence ; messages et traces exclus.
- Notifications : historique des tentatives en échec à partir de la table d'audit des tentatives, sans destinataire ni contenu.
- Application : contrôle SQL ponctuel, versions Laravel/PHP et environnement.

### Limites explicitement signalées dans l'interface

Cette première livraison ne prétend pas mesurer ce qui n'est pas instrumenté :
- Le heartbeat confirme que le processus worker exécute sa boucle ; il ne garantit pas à lui seul que chaque job métier réussit.
- La connectivité réelle des fournisseurs de paiement n'est pas testée par cette page.
- Le P95 affiché est une classe d'histogramme (pas un percentile exact). Le taux 5xx et le temps moyen sont agrégés sur les minutes archivées. L'historique de disponibilité externe nécessite toujours un contrôleur indépendant.
- Les incidents sont regroupés par classe d'exception et fichier/ligne ; les messages, traces et données de requête ne sont pas stockés. Les journaux bruts ne sont volontairement pas consultables depuis l'Admin.
- Aucun retry générique de job n'est proposé : l'idempotence doit être vérifiée par classe de job avant toute reprise.
- Le contrôle d'intégrité est exécuté en tâche planifiée, par lots, et ne supprime aucun fichier. Il vérifie les références de tables connues ; il ne réalise pas un inventaire récursif du disque ni une suppression des fichiers orphelins.

### Sécurité

- Route protégée par permission dédiée.
- Pas d'accès aux payloads queue, traces d'exception, données de notification ou métadonnées de paiement.
- Pas d'appel réel aux fournisseurs de paiement.
- Pas de modification financière, relance générique ou suppression de fichier depuis cette page.
- Les disques distants dont la capacité n'est pas mesurable sont signalés comme non disponibles plutôt que d'afficher une estimation trompeuse.

### Suite technique recommandée

Ajouter un contrôle externe indépendant de disponibilité et des alertes (seuils/canaux) après validation des capacités de l'hébergement cible. Les workers, incidents, métriques HTTP et vérification planifiée des références fichiers sont instrumentés dans cette livraison.
