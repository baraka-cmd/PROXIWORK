# Supervision financière administrateur — points 30 à 34

## Objectif et périmètre

Le module financier administrateur est une couche de consultation et de supervision. Il réutilise les modèles et services financiers existants; il ne possède pas de logique parallèle pour confirmer un paiement, calculer une commission ou modifier un portefeuille.

Les cinq espaces livrés sont :

1. Vue générale des finances.
2. Gestion et consultation des paiements.
3. Consultation des transactions de paiement.
4. Supervision des commissions.
5. Supervision des portefeuilles professionnels.

Les actions de retrait, de remboursement, le registre complet des mouvements et les rapports détaillés restent dans les phases 35 à 38.

## Sources de vérité

| Information | Source |
|---|---|
| Paiement client | \`payments\` / \`Payment\` |
| Tentatives et transactions du fournisseur | \`payment_transactions\` / \`PaymentTransaction\` |
| Événements et transitions de paiement | \`payment_events\` / \`PaymentEvent\` |
| Commission calculée | \`commissions\` / \`Commission\` |
| Solde professionnel actuel | \`wallets\` / \`Wallet\` |
| Écritures du portefeuille | \`wallet_transactions\` / \`WalletTransaction\` |

Une transaction de paiement n'est pas une écriture de portefeuille. Les deux registres ne doivent jamais être fusionnés dans une même liste présentée comme un historique comptable.

## Services

- \`PaymentService\`, \`PaymentWebhookService\`, \`CommissionService\` et \`WalletService\` conservent la responsabilité des opérations métier.
- \`AdminPaymentManagementService\` fournit les lectures administratives du dossier de paiement.
- \`AdminFinanceQueryService\` regroupe uniquement les lectures, filtres, agrégations et aperçus nécessaires aux écrans administrateur.
- Les contrôleurs administrateur valident l'accès et délèguent aux services; ils ne calculent pas les montants.

## Règles financières

- Tous les totaux et graphiques sont groupés par devise. Aucune somme USD/CDF/EUR n'est produite.
- Les paiements confirmés sont déterminés par le statut métier et \`paid_at\`, pas par la simple existence d'une intention.
- Les commissions affichées comme comptabilisées utilisent le statut \`posted\` et la date \`posted_at\`.
- Les soldes de portefeuilles sont des valeurs à l'instant de consultation; ils ne sont pas limités à la période choisie pour les paiements.
- Les graphiques présentent au plus 90 jours de tendance. Les indicateurs de synthèse utilisent toute la période sélectionnée.
- Le taux historique \`commission_rate\` enregistré sur une commission est la référence de cette opération. La valeur \`config('commission.rate')\` ne doit jamais recalculer les anciennes commissions.
- Le taux courant est seulement consultable depuis l'administration, car le projet ne possède pas encore de gestion versionnée et auditée des taux avec date d'entrée en vigueur.
- Les montants ne sont jamais modifiés par une mise à jour directe de solde depuis ces écrans.

## Sécurité et confidentialité

- Chaque section possède sa permission dédiée : \`finance.view\`, \`payments.view\`, \`transactions.view\`, \`commissions.view\`, \`wallets.view\`.
- Les routes sont protégées par authentification, compte actif et middleware de permission.
- Les liens vers d'autres domaines sont affichés uniquement si l'utilisateur possède également la permission cible.
- Les clés d'idempotence, métadonnées brutes des fournisseurs et secrets ne sont pas chargés dans les vues de transaction.
- Les détails des paiements, transactions, commissions et portefeuilles font l'objet d'une trace d'accès administrative.
- Les filtres de montants de paiement exigent une devise; cela évite les comparaisons ambiguës entre monnaies.
- Les listes sont paginées côté serveur et les filtres sont validés par FormRequests.

## Performance

Les écrans utilisent des agrégations SQL, des relations chargées explicitement et des projections de colonnes limitées pour ne pas charger les payloads de fournisseurs inutiles. Des index temporels dédiés couvrent les listes ordonnées par date de paiement, transaction, commission et portefeuille.

## Interventions interdites depuis ces écrans

- Forcer un paiement à \`succeeded\` depuis l'administration.
- Modifier le montant d'un paiement confirmé.
- Modifier directement \`available_balance\`, \`pending_balance\` ou \`locked_balance\`.
- Modifier une commission historique ou son taux appliqué.
- Supprimer ou réécrire une écriture de portefeuille.

Toute correction future devra passer par une opération métier dédiée, transactionnelle, idempotente et auditée.

## Extension dans les phases suivantes

- **35 — Retraits :** workflow de revue et de décision, réservation/libération des fonds, historique de statuts.
- **36 — Remboursements :** demande, décision, exécution confirmée et rapprochement avec le paiement.
- **37 — Mouvements :** registre complet et chronologique des écritures immuables, avec filtres et rapprochement des soldes.
- **38 — Rapports :** agrégations exportables et états financiers, toujours groupés par devise et avec une définition explicite de chaque indicateur.
