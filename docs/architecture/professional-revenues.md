# Module Revenus professionnels

## Périmètre actuel

La page `/professional/revenues` est une interface de consultation. Elle ne crée, ne modifie et ne libère aucun fonds. Les opérations qui affectent le portefeuille restent sous la responsabilité des services de paiement, de portefeuille et de retrait.

Les chiffres affichés sont séparés par devise. Aucune somme n'additionne des devises différentes.

## Définitions utilisées par le tableau de bord

- **Revenus bruts attribués** : somme des `gross_amount` des commissions au statut `posted`, filtrée par `commissions.posted_at`. Ce n'est pas le total des prix actuellement affichés dans les services.
- **Frais de plateforme** : somme des `commission_amount` enregistrés sur ces mêmes commissions. Le taux affiché dans le détail est le taux sauvegardé sur l'opération, et non le taux de configuration actuel.
- **Revenus nets des commissions comptabilisées** : somme des `net_amount` des commissions `posted`, après les frais sauvegardés. Les commissions au statut `reversed` ne sont pas incluses dans ce total. Ce montant reste avant les remboursements confirmés qui n'ont pas encore été rapprochés à une écriture de correction; l'interface signale cette limite au lieu de déduire arbitrairement un montant.
- **Paiements confirmés** : somme des paiements au statut `succeeded` et dont la date `paid_at` appartient à la période, seulement lorsqu'ils sont reliés à une commission encore `posted`. Une tentative initiée, en attente ou échouée n'est pas comptée.
- **Valeur des prestations terminées** : somme de `orders.total` pour les commandes au statut `completed`, filtrée par `completed_at`. Cette valeur commerciale n'est pas présentée comme revenu net.
- **Remboursements confirmés** : somme des remboursements `succeeded` possédant `processed_at` dans la période, rattachés par le paiement à une commission du professionnel. Ils sont affichés et exportés dans une section distincte; ils ne sont pas assimilés à des demandes en attente et ne sont pas déduits des revenus nets tant qu'une écriture de correction liée au portefeuille n'existe pas.
- **Fonds en attente de libération** : `wallets.pending_balance` du professionnel connecté, par devise. C'est un solde actuel du portefeuille, indépendant de la période et des filtres service/commande; il n'est pas ajouté aux revenus de la période.

La date d'une commission, celle du paiement confirmé, celle du remboursement confirmé et celle de la fin de commande peuvent être différentes. Les indicateurs utilisent chacun la date de leur événement et l'interface l'indique. Une période personnalisée est limitée à 366 jours pour éviter des agrégations arbitrairement longues.

## Autorisation et export

Le profil professionnel est résolu depuis l'utilisateur authentifié. Les requêtes de commissions sont toujours limitées à ce profil. La page de détail applique le même périmètre et renvoie une réponse 404 si l'opération appartient à un autre professionnel. L'export CSV reprend les filtres validés de période, devise, service, commande et référence; il indique la période et la devise demandées, utilise le même périmètre professionnel, est diffusé sans cache et protège les colonnes textuelles contre les formules de tableur. Il contient deux sections distinctes : commissions et remboursements confirmés non rapprochés. Le filtre d'état s'applique à l'historique des commissions; la section des remboursements ne contient que les remboursements confirmés. La recherche par référence s'applique aux historiques et à l'export, pas aux indicateurs agrégés.

## Filtres non disponibles faute de registre de mouvements complet

Le filtre par nature de mouvement et un relevé unifié revenus/remboursements/ajustements ne sont pas encore disponibles, car le modèle ne fournit pas un registre unique des écritures financières. L'interface affiche les commissions et les remboursements confirmés dans des sections distinctes, sans simuler une correction comptable qui n'existe pas.

## Limites financières identifiées avant certification

Le modèle existant possède des demandes de remboursement et des champs de renversement sur les commissions, mais il ne fournit pas encore de flux complet qui relie un remboursement effectivement exécuté à une écriture de correction du revenu professionnel et à l'ajustement correspondant du portefeuille. Pour cette raison, une demande de remboursement n'est jamais traitée comme un remboursement exécuté et aucun montant de remboursement n'est déduit arbitrairement du revenu net affiché.

Avant de certifier un relevé financier complet en production, il reste nécessaire de concevoir et tester un flux de rapprochement qui relie un remboursement confirmé, une correction traçable de commission et le mouvement du portefeuille, y compris les remboursements partiels, les doublons et les événements tardifs. Les données de paiement en simulation ne constituent pas une intégration réelle Mobile Money.

## Répartition par service

La commission est enregistrée au niveau de la commande/paiement, pas au niveau de chaque ligne de service. Lorsqu'une commande contient plusieurs services distincts, la totalité de la commission n'est donc pas attribuée à un service arbitraire : elle est affichée dans le groupe « Commande multi-services — ventilation indisponible ». Une ventilation proportionnelle nécessiterait une règle métier explicite, une base d'allocation validée et une gestion exacte des centimes.

## Performance

La page utilise des agrégations SQL et une pagination côté serveur pour les historiques. Les index ajoutés ciblent les filtres professionnels, les dates de comptabilisation, les états, les paiements confirmés, les remboursements confirmés et les commandes terminées. Les données graphiques sont une projection de lecture des enregistrements existants, pas une source financière autonome.
