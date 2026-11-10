# Phase 9 — Audit, sécurité et traçabilité

## Périmètre livré dans cette tranche

Les sept points sont regroupés autour de `audit_logs`, table déjà présente dans PROXIWORK. La migration enrichit ce journal et ajoute uniquement les registres spécialisés qui manquaient : alertes, sessions suivies et interventions administratives.

- **62 — Activités administratives :** événements structurés dans `audit_logs`, avec catégorie, résultat, gravité, corrélation et changements filtrés.
- **63 — Connexions :** les échecs de connexion sont journalisés sans conserver l'adresse e-mail en clair ; les connexions et déconnexions déjà tracées restent dans le même journal.
- **64 — Événements de sécurité :** événements sécurité/authentification consultables dans le centre Admin.
- **65 — Actions sensibles :** métadonnées assainies et comparaison des champs autorisés ; les actions administratives existantes sont projetées dans `sensitive_interventions`.
- **66 — Sessions :** suivi séparé des sessions web Laravel et des jetons Sanctum. Les identifiants de session web sont chiffrés au repos et leur empreinte HMAC est utilisée pour l'indexation. La révocation supprime réellement la session via le handler Laravel configuré (file/database/redis selon son support de destruction) ou le jeton Sanctum.
- **67 — Anomalies :** première règle déterministe : au moins dix échecs de connexion dans une fenêtre de cinq minutes, regroupés par IP ; l'alerte est dédupliquée par empreinte et reste à examiner humainement.
- **68 — Interventions :** les actions `admin.*` existantes sont reliées à une table dédiée, sans remplacer les historiques de paiements, remboursements, retraits ou le registre du portefeuille.

## Règles de sécurité

- Aucun mot de passe, jeton bearer, cookie, identifiant de session brut, destination de retrait ou secret n'est conservé dans les métadonnées.
- Les journaux sont en lecture seule via les routes applicatives. La purge est une opération de rétention dédiée.
- Les alertes ne déclenchent aucune suspension ou révocation automatique.
- Les sessions web et les jetons API sont traités comme deux mécanismes différents. Les changements de mot de passe, réinitialisations et suspensions révoquent les sessions suivies et les jetons historiques du compte. Les sessions web déjà ouvertes avant le déploiement ne sont pas reconstituées ; il faut prévoir une réauthentification globale ou une invalidation initiale des sessions selon le pilote configuré.
- Les changements avant/après sont limités aux champs de statut et de configuration non secrets ; pas de sérialisation complète d'un modèle.
- Les interventions ne constituent pas un ledger financier et ne confirment pas une opération auprès d'un prestataire de paiement.
- La première règle d'anomalie est un seuil initial à calibrer à partir de l'usage réel ; elle n'est pas une preuve d'attaque.

## Permissions

- `security.events.view`
- `security.alerts.view`
- `security.alerts.manage`
- `security.sessions.view`
- `security.sessions.revoke`
- `audit.interventions.view`

## Limites à ne pas masquer

La règle de détection livrée couvre les rafales d'échecs de connexion. La détection IDOR/BOLA, l'analyse comportementale multi-domaines, la double approbation configurable des opérations financières, l'export probant signé et l'archivage externe inviolable nécessitent des lots supplémentaires. Le hash/HMAC protège l'identification et la déduplication, mais ne rend pas la base inviolable face à un administrateur de base de données.
