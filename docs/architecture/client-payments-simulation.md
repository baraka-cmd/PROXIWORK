# Point 21 — Espace client des paiements

## Périmètre appliqué

- Le tableau de bord /client/payments est strictement limité au client authentifié.
- Les filtres sont validés côté serveur : état financier, moyen, passerelle, devise, résultat de tentative, dates de création et de confirmation, référence du paiement, référence de commande et service.
- Les indicateurs sont calculés à partir des données du client et restent séparés par devise.
- Le paiement utilise le service métier et le contrat de passerelle existants ; aucun montant libre envoyé par le navigateur n'est accepté.
- Les tentatives ont un historique distinct. Une erreur réseau est conservée comme résultat inconnu et ne devient pas un échec définitif.
- Les événements de cycle de vie sont conservés dans un journal append-only.
- Un reçu persistant n'est émis qu'après confirmation enregistrée. Il contient un numéro unique, un instantané de l'opération et une empreinte SHA-256 dont l'intégrité est vérifiée avant affichage.
- Les reçus et les paiements sont vérifiés côté serveur selon le propriétaire. Les routes d'initialisation et de demande de remboursement sont limitées en débit.
- Le mode simulé est affiché explicitement et désactivé en production. Aucun vrai numéro de carte, PIN Mobile Money ou secret bancaire n'est demandé.

## Limite métier volontaire sur les remboursements

L'espace client permet de **demander l'examen** d'un remboursement après une réussite simulée. La demande est persistée avec sa référence, son motif, son montant demandé et son état REQUESTED. Elle n'est pas affichée comme un remboursement exécuté et ne change pas l'état du paiement.

La réflexion ne définit pas encore qui est autorisé à examiner, approuver, rejeter ou confirmer un remboursement. Aucun bouton client ne peut donc déclarer un remboursement réussi. La suite nécessitera une politique de décision et un rôle autorisé clairement définis ; l'interface client ne peut pas remplacer cette autorisation.

## Migrations

- payment_receipts : reçu de simulation immuable et vérifiable.
- payment_refunds : demandes de remboursement distinctes du paiement d'origine.
- payment_events : journal des événements de tentative, résultats de passerelle, résultats inconnus, callbacks et confirmations.

Ces tables complètent les tables existantes payment_intents, payments et payment_transactions. Elles ne les remplacent pas.

## Contrôle de validation attendu

La PR doit passer la suite Laravel, le build frontend, Pint sur les fichiers PHP modifiés et les migrations MySQL sur une base neuve. Une réussite de CI ne certifie pas l'intégration d'un prestataire réel ; aucun agrégateur réel n'est configuré dans ce point.
