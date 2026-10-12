# PROXIWORK — Centre de décision des devis client

## Responsabilités

- `/client/quotes` présente les devis accessibles au client connecté, leurs propositions actives, les échéances et les décisions enregistrées.
- `/client/requests` reste la référence pour le cycle de vie de la demande et son adresse d'exécution.
- `/client/orders` devient la destination après acceptation lorsqu'une commande a été créée.
- `/client/payments` reste séparé : l'acceptation commerciale ne signifie jamais que le paiement a été exécuté.

## Modèle et historique existants

La fonctionnalité réutilise `Quotation`, `QuotationOffer`, `QuotationEvent`, `QuotationService`, `QuotationNegotiationService` et `OrderService`. Une offre est une version append-only : une contre-proposition crée une nouvelle ligne `quotation_offers` et met à jour seulement la référence `current_offer_id`. Les anciennes versions restent consultables.

Les statuts utilisés sont les valeurs existantes de `QuotationStatus` : `sent`, `negotiating`, `accepted`, `rejected`, `expired` et `withdrawn`. Le statut de la demande et celui de la commande restent indépendants.

## Décisions et négociation

- Le client ne peut accepter ou refuser qu'une offre courante créée par le professionnel.
- Le professionnel peut accepter ou refuser une contre-proposition courante créée par le client, ou répondre par une nouvelle version.
- Une partie ne peut pas envoyer une nouvelle proposition tant que l'autre partie n'a pas répondu à l'offre courante.
- La devise reste identique au cours d'une négociation : sans taux de conversion ni règle de conversion définis par le métier, les montants de devises différentes ne sont pas comparables.
- Les transitions sont validées côté serveur, sous verrou transactionnel ; le statut n'est jamais accepté depuis un champ envoyé par le navigateur.
- L'offre acceptée est enregistrée dans `accepted_offer_id` et sert de source pour le montant, la devise, le délai et les conditions contractuelles de la commande.
- La création de commande et l'acceptation de l'offre s'exécutent dans la même transaction. Les contraintes uniques existantes sur commande, devis, demande et offre acceptée complètent la protection contre les doublons.

## Expiration

`QuotationExpirationService` expire les devis dont l'offre active a atteint sa date limite. Le service verrouille et revérifie chaque devis avant de modifier son statut, enregistre un événement système `quote_expired` et notifie les deux parties après la transaction. L'opération est idempotente.

La commande `quotations:expire` est planifiée toutes les heures et empêche les chevauchements. Le tableau de bord client exécute également le même service limité à ce client afin de rester cohérent si le planificateur a pris du retard. Les opérations d'acceptation et de négociation contrôlent elles-mêmes la validité ; le planificateur n'est pas la barrière de sécurité.

## Liste et filtres

Les filtres sont validés dans `IndexClientQuotationsRequest` et appliqués en SQL avant pagination. Les statistiques sont calculées sur les devis du client authentifié. La recherche couvre la référence affichée, le titre de la demande, le titre du service et le nom du professionnel. Le tri par montant regroupe d'abord par devise et ne compare pas directement USD et CDF.

## Notifications et audit

Les décisions de création, d'acceptation, de refus et de contre-proposition sont auditées par les contrôleurs Web/API existants. Les notifications sont déclenchées après les transitions métier réussies. L'expiration est historisée par un événement sans acteur utilisateur, car elle est produite par le système.

## Contrôles attendus en CI

- Isolation des devis par client et par professionnel.
- Refus d'accepter une offre expirée, remplacée ou créée par le demandeur lui-même.
- Alternance des tours de négociation et maintien de la devise.
- Expiration idempotente et notifications aux deux parties.
- Acceptation d'une contre-proposition client par le professionnel avec création d'une seule commande en attente de paiement.
- Tests de pagination, filtres, affichage de l'historique, migrations MySQL fraîches, build frontend et Pint.
