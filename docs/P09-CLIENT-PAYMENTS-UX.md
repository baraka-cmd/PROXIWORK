# P09 — Revue et modernisation UI/UX des paiements client (C25–C29)

## Portée et méthode

Le travail part de `audit/current-project-baseline` et ne modifie que les vues Blade des paiements client, leur feuille CSS partagée, le JavaScript déjà chargé par le module et cette documentation. Les routes, contrôleur, FormRequests, services, modèles, policies, enums, migrations et règles financières ne sont pas modifiés.

## Logique backend observée en lecture seule

- La liste limite les paiements au client connecté, calcule les agrégats côté serveur et groupe chaque somme par devise. Les filtres autorisés proviennent de `IndexClientPaymentsRequest`; les résultats sont paginés avec `withQueryString()`.
- Le détail et le reçu vérifient l’appartenance au client. Le reçu n’est présenté qu’après vérification serveur de son empreinte et de son rattachement à un paiement et une tentative confirmés dans la simulation.
- Le service de remboursement impose un paiement réussi, une raison validée côté serveur, le calcul du montant remboursable côté serveur et une seule demande ouverte. Le statut initial est `requested`; il ne signifie pas que le remboursement a été exécuté.
- Les états `unknown` appartiennent aux tentatives et imposent la prudence : le client doit vérifier la même opération et éviter de lancer un doublon.

## Changements UI/UX

- **C25 — Historique :** critères courants séparés des critères avancés, recherche avec limites annoncées, erreurs de dates visibles, état vide distinct selon présence de filtres, montants alignés et statuts textuels.
- **C26 — Détail :** événements inconnus et transitions de statut présentés avec un libellé neutre, message d’échec utilisateur maîtrisé au lieu d’afficher le texte brut de passerelle, détails de diagnostic secondaires, et rappel explicite pour les résultats incertains.
- **C27 — Reçu :** hiérarchie documentaire conservée, empreinte copiable si le navigateur l’autorise, impression renforcée (marges, contraste noir et blanc, masquage des actions, prévention des coupures de sections et gestion des références longues).
- **C28 — Remboursement :** synthèse du paiement concerné, raison conservée via `old()`, erreurs associées au champ et résumé accessible, compteur indicatif, confirmation serveur mise en évidence et carte explicite pour une demande déjà ouverte.
- **C29 — Cohérence :** libellés textuels distincts pour paiement, tentative et remboursement ; les états inconnus ne révèlent pas les identifiants techniques bruts.

Les styles ajoutés sont spécifiques au module de paiement ou au document de reçu, afin de limiter les effets de bord dans les vues de commandes qui chargent aussi `orders-payments.css`.

## Références UX

- Stripe, états de remboursement : https://support.stripe.com/questions/understanding-refund-statuses?locale=en-GB
- GOV.UK Design System, résumé des erreurs : https://design-system.service.gov.uk/components/error-summary/
- GOV.UK Design System, messages d’erreur et conservation des réponses : https://design-system.service.gov.uk/components/error-message/
- WANDR, patterns de tableaux de bord fintech (hiérarchie, lisibilité et confiance) : https://www.wandr.studio/blog/fintech-dashboard-design-patterns

Ces références inspirent la présentation et l’accessibilité ; elles ne remplacent pas les règles métier propres à PROXIWORK. Aucun délai de remboursement ni aucune promesse d’un prestataire réel n’est transposé au simulateur.

## Vérifications et limites

- Audit des fichiers backend et vues sur la branche de base effectué en lecture seule.
- Les changements n’ajoutent aucune dépendance et n’effectuent aucun calcul financier côté navigateur.
- La CI GitHub doit être consultée après le commit final pour confirmer la compilation et les tests automatisés.
- Aucun rendu visuel navigateur, test manuel responsive, test clavier/lecteur d’écran ou impression physique ne peut être déclaré effectué sans exécution réelle de ces vérifications.
