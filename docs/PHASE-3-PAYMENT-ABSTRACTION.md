# Phase 3 — 5.7 Payment Abstraction

## Objectif

Introduire une frontière stable entre le domaine PROXIWORK et les fournisseurs de paiement, sans mettre de logique M-Pesa/Airtel/Stripe dans les contrôleurs ou services métier.

Client → PaymentController → PaymentService → PaymentIntent → PaymentGatewayManager → PaymentGateway → Provider

## Sécurité d'idempotence

Une demande de paiement exige l'en-tête Idempotency-Key.

La clé est persistée avec le client, la commande, une empreinte de l'opération, la méthode, le fournisseur, le montant, la devise et le statut.

La base impose l'unicité de client_id + idempotency_key. L'empreinte empêche de réutiliser une même clé pour une autre opération.

## Coupure réseau

Scénario critique :

1. Le client envoie le paiement avec la clé A.
2. PROXIWORK crée PaymentIntent A = initiated.
3. PROXIWORK appelle le fournisseur avec A.
4. Le fournisseur peut accepter le paiement.
5. La connexion coupe avant la réponse finale.
6. Le client ne sait pas si le paiement est passé.
7. Le client renvoie exactement la même clé A.

PROXIWORK ne crée pas un deuxième intent. Le gateway est rappelé avec la même clé d'idempotence ; les futurs fournisseurs réels devront donc supporter l'idempotence côté API.

Une nouvelle clé pour la même commande alors qu'un paiement est encore initiated, pending ou processing est refusée.

## Transaction DB vs appel fournisseur

L'appel réseau au fournisseur est volontairement exécuté hors transaction SQL.

BEGIN → lock order → validate → create PaymentIntent → COMMIT → CALL PROVIDER → BEGIN → lock PaymentIntent → persist normalized result → COMMIT

Cela évite de conserver un verrou SQL pendant un appel réseau potentiellement lent. Les transactions Laravel assurent l'atomicité des écritures et les verrous pessimistes protègent les opérations concurrentes.

## Montant

Le client ne fournit jamais le montant réel du paiement. Le montant vient exclusivement de Order.total et Order.currency.

Le fournisseur doit retourner le même montant et la même devise ; sinon le paiement est marqué failed.

## Ce qui reste pour 5.8

5.8 ajoutera payment_transactions, historique des tentatives, références fournisseur durables, vérification de statut, webhooks, vérification serveur-à-serveur, rapprochement et transition sécurisée payment succeeded → order confirmed.

5.9 renforcera ensuite l'idempotence et la concurrence sur l'ensemble du domaine financier.

## Règle critique

Ne jamais considérer une redirection frontend comme preuve d'un paiement réussi. La confirmation finale doit venir du fournisseur et être vérifiée côté serveur.