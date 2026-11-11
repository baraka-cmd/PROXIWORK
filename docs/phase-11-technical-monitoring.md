# Phase 11 — Supervision technique

## Implémentation initiale des points 77 à 83

Cette livraison ajoute une première vue de supervision intégrée à l'administration PROXIWORK. Elle réutilise les tables et services déjà présents : queue Laravel en base, `failed_jobs`, `notification_deliveries`, `payments`, `payment_transactions`, stockage Laravel et journaux applicatifs.

### Données effectivement observées

- Files database queue : volumes en attente et réservés, ancienneté de la tâche en attente la plus ancienne.
- Jobs échoués : dix dernières références, nom de classe réduit, file et date d'échec. Payload et stack trace non exposés.
- Notifications : volumes par statut, avec lien vers l'historique détaillé déjà existant.
- Paiements : agrégation par fournisseur et statut, transactions inconnues.
- Stockage local/public : accès au disque et capacité système lorsqu'elle est disponible.
- Journaux : présence, taille et date de dernière écriture du fichier de log, sans exposition de son contenu.
- Application : contrôle SQL ponctuel, versions Laravel/PHP et environnement.

### Limites explicitement signalées dans l'interface

Cette première livraison ne prétend pas mesurer ce qui n'est pas instrumenté :
- L'activité réelle des workers lorsqu'ils sont inactifs n'est pas prouvée par la table `jobs`.
- La connectivité réelle des fournisseurs de paiement n'est pas testée par cette page.
- Les percentiles HTTP, taux d'erreur applicatif agrégé et historique de disponibilité nécessitent une collecte dédiée et, pour la disponibilité externe, un contrôleur indépendant.
- Les erreurs applicatives ne sont pas encore regroupées en incidents ; les journaux bruts ne sont volontairement pas consultables depuis l'Admin.
- Aucun retry générique de job n'est proposé : l'idempotence doit être vérifiée par classe de job avant toute reprise.
- Aucun inventaire récursif ni nettoyage de fichiers orphelins n'est exécuté dans une requête web.

### Sécurité

- Route protégée par permission dédiée.
- Pas d'accès aux payloads queue, traces d'exception, données de notification ou métadonnées de paiement.
- Pas d'appel réel aux fournisseurs de paiement.
- Pas de modification financière, relance générique ou suppression de fichier depuis cette page.
- Les disques distants dont la capacité n'est pas mesurable sont signalés comme non disponibles plutôt que d'afficher une estimation trompeuse.

### Suite technique recommandée

Instrumenter les événements queue avec un heartbeat worker explicite, créer des instantanés de métriques à faible fréquence, collecter des métriques HTTP agrégées avec rétention, ajouter un registre d'incidents expurgé des secrets, et connecter un contrôle de disponibilité externe. Ces éléments doivent être introduits après validation de l'impact opérationnel et des capacités de l'hébergement cible.
