# Supervision financière avancée — points 35 à 38

## Périmètre
Cette évolution complète les opérations financières administratives sans créer de second portefeuille, de second registre ou de second workflow de retrait.

- 35 — Retraits : la demande et les transitions restent dans WithdrawalService. L'interface administrative ajoute la recherche, les filtres, les compteurs par état, les réservations groupées par devise et une permission dédiée à la révélation de la destination. Mobile Money et virement restent des traitements manuels tant qu'aucun fournisseur de versement réel n'est intégré.
- 36 — Remboursements : PaymentRefundAdminService orchestre la prise en charge, le refus, la confirmation ou l'échec. Les demandes client existantes et PaymentRefund sont conservés. Les transitions sont enregistrées dans PaymentEvent, avec une clé d'événement déterministe et une empreinte de la note plutôt que son contenu dans les métadonnées.
- 37 — Mouvements : le registre est wallet_transactions, consulté en lecture seule via AdminFinancialOperationsQueryService. Les colonnes sensibles metadata et idempotency_key ne sont pas sélectionnées. Les corrections restent de nouvelles écritures métier, jamais des modifications du registre.
- 38 — Rapports : paiements confirmés par paid_at, remboursements réussis par processed_at, commissions postées par posted_at, retraits réussis par completed_at. Les soldes de portefeuille sont une photographie actuelle. Tous les agrégats sont séparés par devise.

## Sécurité et cohérence
- Routes sous authentification, compte actif et permissions RBAC séparées.
- Les décisions de remboursement verrouillent le paiement puis la demande, dans le même ordre que le flux client, afin de réduire les interblocages.
- Les montants cumulés remboursés sont comparés en unités mineures (centimes) et ne peuvent dépasser le paiement d'origine.
- Une confirmation de remboursement exige une référence externe et une note de rapprochement. Elle ne prétend pas appeler un fournisseur absent.
- Les transitions financières sont transactionnelles et auditées. Les références et notes brutes ne sont pas copiées dans les journaux d'audit; une empreinte de la note est conservée dans les événements.
- Les exports CSV sont diffusés en flux, marqués privés/no-store et neutralisent les cellules susceptibles d'être interprétées comme formules. Ils incluent les opérations réussies ainsi que trois lignes de photographie actuelle par portefeuille (disponible, en attente, bloqué), clairement étiquetées comme instantanés.
- Les filtres sont validés côté serveur, les listes sont paginées, les dates métier sont explicites et les devises ne sont jamais additionnées entre elles.

## Limites explicites
- Aucun versement ou remboursement externe automatique n'est déclaré intégré.
- Aucun statut du paiement initial n'est modifié à la suite d'un remboursement : la politique de remboursement total/partiel et les effets sur la commande doivent être définis séparément.
- Le registre wallet_transactions couvre les mouvements des portefeuilles professionnels, pas une comptabilité générale complète.
- Les rapports sont opérationnels et ne sont pas présentés comme des états comptables certifiés.
- Les transitions historiques des remboursements utilisent PaymentEvent; aucun deuxième historique redondant n'est créé.

## Validation
Les tests ciblés AdminFinancialOperationsTest couvrent l'accès administrateur, le refus d'accès client et les en-têtes d'export CSV. La suite complète, le build Vite, Pint, les migrations MySQL et les tests de concurrence doivent être exécutés dans CI avant de considérer la phase comme validée.
