# P09 — Modernisation UI/UX des commandes et de la livraison (C17–C24)

## Objectif et périmètre

Moderniser les écrans client des commandes sans réécrire le workflow métier. Le travail est limité aux vues Blade, au CSS/JS du module et à cette documentation. Les contrôleurs, services, FormRequests, modèles, politiques, routes, paiements et contrôles de téléchargement restent inchangés.

## Audit backend en lecture seule

Fichiers consultés sur `audit/current-project-baseline` :

- `routes/web.php` — routes existantes pour liste/détail/paiement, planning, acceptation/correction de livraison et téléchargement privé.
- `app/Http/Controllers/Web/Client/OrderController.php` — filtres validés, pagination `withQueryString()`, vérification de propriété et orchestration du workflow.
- `app/Services/Order/OrderWorkflowService.php` — transitions atomiques, validation de l’état, confirmation des dates, acceptation et correction.
- `app/Http/Controllers/Web/OrderDeliveryAttachmentController.php` — autorisation sur la commande, association fichier-commande, stockage privé et en-tête `nosniff`.
- `RequestOrderScheduleChangeRequest` et `RequestOrderDeliveryChangesRequest` — motif requis, 10–2 000 caractères.
- `IndexClientOrdersRequest` — filtres et valeurs autorisées.
- Vues `index.blade.php`, `show.blade.php`, `payment.blade.php`, CSS et JS existants.

Constats préservés : les statuts commande/paiement sont séparés ; le montant affiché provient de la commande ; le paiement de cette vue est une simulation conditionnelle ; le client ne peut confirmer qu’un planning proposé valide ; l’acceptation de livraison termine la commande ; une demande de correction repasse la commande en réalisation ; les fichiers restent privés et téléchargés via la route autorisée.

## Changements front-end

### C17 — Liste

- Hiérarchie de page et action « Découvrir les services ».
- Statistiques avec état courant annoncé par `aria-current`.
- Filtres actifs retirables individuellement en conservant les autres critères validés.
- Distinction entre aucune commande enregistrée et une recherche sans résultat.
- Libellés de repli neutres pour les statuts inconnus, actions de suivi plus explicites et mise en page mobile renforcée.

### C18 — Détail

- Carte « Prochaine étape » dérivée du statut réellement enregistré et des données de planning/paiement disponibles.
- Les cas paiement, proposition de planning, réalisation, livraison soumise, commande terminée, annulée ou contestée sont distingués.
- Les valeurs techniques inconnues ne sont pas montrées comme libellés utilisateurs.
- Planning confirmé/proposé présenté dans des cartes de dates, avec fuseau horaire applicatif explicite.
- Historique et fichiers de livraison plus lisibles ; absence de fichiers explicitement signalée.

### C19 — Paiement

- Récapitulatif du montant et de la référence, sans champ de montant côté navigateur.
- Méthodes simulées présentées comme options accessibles.
- Message distinct selon que la simulation est activée ou désactivée ; aucun fournisseur réel n’est suggéré.
- Erreurs regroupées dans un résumé accessible ; indication que le résultat dépend du statut enregistré.

### C20–C21 — Planning

- Dates proposées et confirmées plus faciles à comparer.
- Explication du fuseau horaire.
- Libellés et aide plus précis pour le motif de modification, compteur de caractères et conservation de la saisie.
- Le navigateur ne modifie aucune date métier.

### C22–C24 — Livraison et pièces jointes

- La carte de livraison rappelle que l’acceptation termine la commande.
- Formulaire de correction plus guidé, limite 10–2 000 caractères conservée.
- Fichiers documentaires, taille disponible et mention de téléchargement privé.
- Aucun aperçu public, téléversement client ou nouveau point d’accès n’est ajouté.

### Transversal

- CSS limité au fichier du module commandes/paiements.
- JS léger pour l’état de soumission, les compteurs de caractères et le focus du résumé d’erreurs.
- Pas de nouvelle dépendance.
- Pas de modification backend.

## Inspirations consultées

- Upwork, soumission et consultation des livrables : https://support.upwork.com/hc/en-us/articles/211068368-How-to-submit-work-and-milestones-to-your-client
- Upwork, examen du travail remis : https://support.upwork.com/hc/en-us/articles/360000980507-Review-and-pay-for-fixed-price-contracts-and-milestones
- Fiverr, états de commande, acceptation et révisions : https://help.fiverr.com/hc/en-us/articles/37552517993105-The-complete-guide-to-your-Fiverr-order-Statuses-and-process
- GOV.UK, résumé d’erreurs accessible : https://design-system.service.gov.uk/components/error-summary/
- GOV.UK, formulaire de téléversement : https://design-system.service.gov.uk/components/file-upload/

## Validation et limites

- Le backend n’a pas été modifié.
- Aucune exécution de navigateur ni vérification visuelle responsive réelle n’a été réalisée depuis le connecteur GitHub.
- Les tests et la compilation Vite doivent être confirmés par GitHub Actions après le dernier commit.
- Les parcours nécessitant un compte (paiement, planning, livraison, autorisation des pièces jointes) doivent être vérifiés manuellement dans un environnement de test.
- La PR ne doit pas être fusionnée sans revue et autorisation explicite.
