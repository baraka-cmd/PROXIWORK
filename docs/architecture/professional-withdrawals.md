# Retraits professionnels — point 35

## Statut de ce document

Audit initial et contrat d'architecture du point 35, mis à jour au fil de l'implémentation. Ce document ne certifie pas le module pour la production. Les choix commerciaux non prouvés par le code restent explicitement non configurés.

## Implémentation engagée sur la branche du point 35

- Migration additive : historique des statuts, compartiment de solde par nouvelle écriture, destination chiffrée, frais/net nullable et métadonnées de vérification.
- Cycle de vie ajouté dans le service : `requested`, `processing`, `review_required`, `succeeded`, `failed`, `cancelled`; transitions contrôlées et historiques idempotents.
- Réservation enregistre séparément le débit du disponible et le crédit du bloqué. Succès, échec, refus et annulation créent leurs écritures terminales dans la même transaction que la mise à jour des soldes.
- Le flux wallet existant identifie maintenant les écritures de revenu en attente et enregistre la libération en deux lignes idempotentes : débit du compartiment `pending` puis crédit du compartiment `available`. Les clés d'idempotence de crédit/libération refusent la réutilisation avec un montant ou une référence différente.
- Le formatage des montants du service Wallet et du calcul de commission évite désormais la conversion de centimes entiers en flottants binaires.
- Verrouillage normalisé dans l'ordre wallet puis retrait pour les mutations qui touchent les deux lignes.
- Nouvelles destinations chiffrées; le champ historique requis par le schéma contient seulement une valeur masquée pour les nouvelles demandes. L'écran professionnel ne révèle que la destination masquée; l'accès financier admin à la valeur complète est journalisé.
- L'écran de création ne crée plus de wallet en GET. Le service refuse les retraits si le wallet n'est pas actif.
- Espace admin manuel protégé par `admin.withdrawals.manage`, avec note de décision obligatoire, refus/libération, démarrage du traitement, rapprochement de succès/échec et mise en vérification.
- Les signalements depuis le détail du retrait créent un ticket de support lié par clé étrangère au retrait. La conversation est visible par le professionnel et le support; les réponses sont autorisées uniquement au propriétaire du ticket ou à un agent habilité.
- Les montants affichés et le récapitulatif client ne passent pas par des nombres flottants. Les frais et le net restent non renseignés tant qu'un barème fiable n'existe pas.
- Le moyen de retrait sélectionné est une préférence de traitement manuel; aucun fournisseur de versement réel n'est appelé.

## Périmètre de code audité

Branche de départ : `feature/professional-wallet-point-34`, HEAD `166d51f87498f13081b98ecb4eb8c936b28c21e7`.

Fichiers examinés :
- `app/Http/Controllers/Web/Professional/WithdrawalController.php`
- `app/Http/Requests/Wallet/StoreWithdrawalRequest.php`
- `app/Services/Wallet/WithdrawalService.php`
- `app/Services/Wallet/WalletService.php`
- `app/Models/Withdrawal.php`, `Wallet.php`, `WalletTransaction.php`
- `app/Enums/WithdrawalStatus.php`, `WalletTransactionType.php`, `PaymentProvider.php`
- `app/Policies/WithdrawalPolicy.php`, `WalletPolicy.php`
- migrations des tables `wallets`, `wallet_transactions`, `withdrawals`
- routes Web, vues Blade, JavaScript/CSS et tests `WebProfessionalWalletWithdrawalsTest` et `PaymentTransactionsWalletTest`
- issue #137 relative aux écritures manquantes du registre.

## Faits établis par le code actuel

1. Les tables et modèles financiers existent déjà. Le point 35 doit les faire évoluer prudemment, sans créer un deuxième wallet ou une deuxième table de retraits.
2. `WithdrawalService::request()` verrouille le wallet et réalise réservation, demande et écriture initiale dans une transaction DB. La clé d'idempotence est unique par professionnel et son empreinte compare montant, devise, canal et destination.
3. Le service ne vérifie pas encore le statut actif du wallet au moment de la réservation.
4. `markSucceeded()` et `markFailed()` verrouillent les lignes et changent les soldes, mais ne créent pas toutes les écritures de registre nécessaires à chaque transition. Les écritures actuelles ne désignent pas le compartiment auquel `balance_before` / `balance_after` se rapporte.
5. Les statuts actuels sont `requested`, `processing`, `succeeded`, `failed` et `cancelled`. Il n'existe pas encore de transition explicite complète pour démarrer le traitement, annuler, ou marquer un résultat incertain.
6. La route GET `/professional/withdrawals/create` appelle `WalletService::getOrCreate()`; une consultation peut donc créer un portefeuille et doit être rendue sans effet de bord.
7. La validation accepte une chaîne libre pour le canal et la destination. La vue propose Mobile Money et virement bancaire, mais le code de paiement expose uniquement le fournisseur `fake`, destiné aux paiements entrants simulés. Aucun fournisseur réel de versement n'est établi par l'audit.
8. La destination est actuellement stockée en clair et affichée en clair dans le détail. Elle doit être protégée pour les nouvelles demandes et masquée à l'écran.
9. Les vues de retrait convertissent les montants décimaux en `float` pour l'affichage. Cela doit être remplacé par un formatage décimal sans flottant.
10. Aucun minimum, maximum métier, plafond quotidien/périodique, barème de frais, délai contractuel ou workflow d'approbation financière n'est prouvé par les fichiers audités. Ne pas en inventer.
11. Les tests existants couvrent la réservation de base, l'isolation des retraits, la restitution après échec et l'idempotence simple. Ils ne certifient pas la concurrence multi-connexion, les écritures terminales, l'historique des statuts, les annulations, les résultats incertains, les règles de frais ou un versement réel.

## Garde-fous non négociables

- La demande, la réservation, l'ordre de paiement et la confirmation de réception sont des événements distincts.
- Un contrôleur Web ne modifie jamais directement les soldes ou le statut financier.
- Une requête GET ne crée pas de wallet et ne réserve aucun montant.
- Une demande est bornée au profil professionnel authentifié et au wallet correspondant à la devise.
- Le montant demandé est analysé en décimal exact; aucun calcul monétaire ne dépend de `float`.
- La réservation et son écriture de registre sont atomiques.
- Chaque transition de retrait et chaque mouvement de compartiment possède une clé d'idempotence déterministe, une référence de retrait et une trace d'état.
- Un succès n'est jamais déduit d'une redirection navigateur, d'un clic professionnel, d'un timeout ou d'une réponse non authentifiée.
- Un résultat ambigu devient « à vérifier » et ne déclenche pas un deuxième transfert automatiquement.
- Les écritures historiques ne sont ni supprimées ni réinterprétées silencieusement. Les anciens soldes avant/après restent « compartiment inconnu » tant qu'une preuve ne permet pas de les classifier.
- Le fournisseur `fake` ne doit jamais être présenté comme un fournisseur de retrait réel.
- Une destination de paiement n'est pas modifiable par le professionnel après la soumission.
- Les frais/net ne sont affichés comme connus que si une politique tarifaire serveur est configurée et versionnée.
- Les opérations financières d'un administrateur doivent nécessiter une permission dédiée, un motif et une trace d'audit.

## Cycle de vie cible

- `requested`: demande créée et montant réservé; aucune preuve qu'un paiement a été envoyé.
- `processing`: un opérateur autorisé ou un adaptateur de paiement a effectivement démarré l'exécution.
- `succeeded`: preuve fiable d'exécution vérifiée, avec référence de rapprochement; libération du compartiment bloqué et écriture de registre atomiques.
- `failed`: échec d'un paiement déjà en traitement, confirmé et non ambigu; libération unique du compartiment bloqué vers le disponible et écritures de registre atomiques.
- `rejected`: demande refusée avant le démarrage du traitement; motif enregistré et libération unique de la réservation.
- `cancelled`: annulation autorisée avant l'envoi irréversible du paiement; libération unique de la réservation.
- `review_required`: état présent dans l'énumération actuelle; résultat non déterminé ou incohérence nécessitant un rapprochement humain. Aucun second envoi automatique et aucun déblocage automatique tant que l'issue du transfert n'est pas connue.

Transitions autorisées imposées par le service actuel :
- requested → processing, rejected après décision admin autorisée, cancelled uniquement avant exécution.
- processing → succeeded ou failed uniquement après preuve; processing → review_required si le résultat est ambigu.
- review_required → succeeded ou failed seulement après rapprochement documenté.
- Les états finaux sont immuables pour les appels ordinaires; une correction doit être une opération distincte, tracée et compensatoire.

## Registre et compartiments

La migration additive du point 35 ajoute l'indicateur `balance_bucket` aux écritures. Valeurs prévues : `available`, `pending`, `locked`, `unknown`. Les anciennes lignes sont initialisées à `unknown`; aucun historique n'est recalculé à partir d'une supposition.

Pour une réservation, l'écriture initiale documente la diminution de `available`; l'augmentation de `locked` est représentée séparément si le modèle du registre retient une ligne par compartiment. En cas d'échec/annulation, l'écriture documente à la fois la diminution de `locked` et l'augmentation de `available`. En cas de succès, le montant quitte `locked` et la preuve de versement est conservée. Toutes les écritures et modifications de soldes sont validées dans la même transaction DB.

## Décisions commerciales non confirmées

| Décision | Résultat de l'audit |
| --- | --- |
| Canaux réels de versement | Aucun adaptateur de versement réel identifié; ne pas déclarer Mobile Money/bancaire comme intégrés. |
| Exécution | Le traitement manuel est possible conceptuellement, mais le workflow d'approbation/rapprochement admin reste à mettre en place. |
| Frais et net | Aucun barème fiable identifié; ne pas afficher un montant net fictif. |
| Éligibilité et limites | Minimum, maximum métier, plafonds temporels et vérification d'identité doivent être explicitement configurés avant activation. |
| Délai annoncé | Aucun SLA prestataire démontré; afficher uniquement un délai indicatif configuré et justifié, sinon ne pas promettre. |
| Destinations | Aucun modèle de destination vérifiée identifié; ne pas inventer une gestion de comptes bancaires/mobile money enregistrés. |

## Critères de sortie du point 35

- Tests de propriétaire/IDOR, wallet absent/inactif, devise, montants, idempotence, transitions illégales, double libération, double succès, échec et annulation.
- Tests de registre pour chaque compartiment et cohérence wallet/withdrawal/transactions.
- Tests de répétition et d'ordre tardif des événements; aucun succès depuis un client.
- Tests UI : états vide, en attente, traitement, succès, échec, vérification, destination masquée, messages accessibles, responsive, pagination et filtres.
- CI verte sur le dernier HEAD : tests Laravel, migration MySQL vierge, build Vite, Composer validation et Pint.
- La fonctionnalité n'est pas déclarée « paiement réel prêt » tant qu'un fournisseur réel ou un processus manuel autorisé, documenté et testé n'est pas configuré.
