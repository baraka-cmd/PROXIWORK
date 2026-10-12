# Cycle de vie des demandes de service

## Périmètre

Une demande de service est le dossier initial du client. Elle n'est ni un devis, ni une commande, ni un paiement. Le modèle et l'enum existants sont la source de vérité pour les états réellement pris en charge.

La référence d'affichage/API est calculée à partir de l'identifiant : `PW-REQ-000001`. Elle n'est pas une colonne séparée.

## États actuellement pris en charge

| État | Signification | Action suivante habituelle |
| --- | --- | --- |
| `draft` | Le client prépare sa demande, elle n'a pas encore été envoyée | Modifier, envoyer ou annuler |
| `requested` | La demande est envoyée au professionnel | Attendre une réponse, annuler ou recevoir un devis |
| `quoted` | Un devis existe et attend la décision du client | Examiner/négocier/accepter/refuser le devis |
| `accepted` | Le devis a été accepté et la demande a atteint son issue positive | Suivre la commande distincte créée à partir du devis accepté |
| `rejected` | Le professionnel ou le client a refusé la suite commerciale | Consulter l'historique |
| `cancelled` | Le client a annulé la demande à un état autorisé | Consulter l'historique |

Les états `examined`, `expired` et `completed` ne sont pas des états de demande définis par l'enum actuel. Ne pas les simuler dans l'interface ni les ajouter sans décision métier et migration/test coordonnés. L'expiration d'une offre est gérée par le devis, pas par un état inventé de demande.

## Transitions

- `draft → requested` : le client soumet la demande ; le serveur vérifie de nouveau que le service est encore éligible à recevoir des demandes.
- `draft → cancelled` : le client annule son brouillon.
- `requested → cancelled` : le client annule avant la réponse finale du professionnel.
- `requested → rejected` : le professionnel destinataire refuse la demande.
- `requested → quoted` : le professionnel crée un devis ; la création du devis et la transition de la demande sont transactionnelles.
- `quoted → accepted` : le client accepte une offre valide ; l'état du devis, l'état de la demande et la création de la commande sont coordonnés dans la transaction métier.
- `quoted → rejected` : le client refuse le devis ; le devis et la demande sont mis à jour et l'événement est historisé.

Toute autre transition est refusée par le service de cycle de vie ou par la politique métier existante. Les statuts ne sont jamais acceptés directement depuis les champs d'entrée du client.

## Éligibilité d'un service

La création d'une nouvelle demande exige un service publié avec une date de publication, une catégorie active, un profil professionnel actif/public/vérifié, une date de vérification et un compte utilisateur professionnel actif. Ces conditions sont appliquées dans la page de création, la validation serveur et le service métier. Elles sont recontrôlées lors de la soumission d'un brouillon.

Une demande historique reste consultable même si le service est ensuite dépublié ou si sa catégorie devient inactive. L'éligibilité au démarrage d'une nouvelle demande ne doit pas effacer l'historique d'un dossier déjà créé.

## Autorisations et données

- Les listes client sont toujours limitées au client authentifié.
- Les listes professionnelles sont toujours limitées au profil professionnel correspondant à l'utilisateur authentifié.
- Les détails, modifications, soumissions, annulations et refus passent par les politiques d'autorisation.
- Une adresse utilisée dans une demande doit appartenir au client. Comme la création de commande exige une adresse, le client peut en sélectionner/modifier une tant que la demande est `requested` ou `quoted`. L'adresse ne peut pas être changée via cette action après acceptation du devis.
- Le statut et le destinataire professionnel sont définis côté serveur.
- Les budgets minimum/maximum sont cohérents et une devise est obligatoire lorsqu'un budget est fourni.
- La création web et API accepte une clé d'idempotence UUID facultative. La contrainte unique `(client_id, idempotency_key)` permet de retrouver le brouillon existant en cas de reprise/double envoi.
- Les événements de changement de statut sont conservés dans `service_request_status_histories`. Les actions métier web/API sont aussi consignées dans les journaux d'audit existants.
- Les notifications de création, annulation et refus utilisent le service de notifications existant. L'échec d'envoi d'une notification ne doit pas annuler une transition déjà enregistrée.

## Recherche, statistiques et interface

Les listes client et professionnelle filtrent côté base de données avant pagination. Les filtres disponibles sont statut, recherche textuelle, professionnel (liste client), catégorie, date de début, date de fin et ordre chronologique. Les paramètres validés sont conservés dans les liens de pagination.

Les statistiques client sont calculées uniquement sur les demandes du client courant :
- **Toutes** : nombre total de demandes ;
- **En attente du professionnel** : état `requested` ;
- **Réponses à consulter** : état `quoted` ;
- **Clôturées** : états `accepted`, `rejected` et `cancelled`.

L'état `accepted` signifie que le workflow de demande est terminé et que le suivi se poursuit dans les commandes. Il ne signifie pas que la prestation ou le paiement est terminé.

## Limites connues à ne pas masquer

- Le schéma actuel ne définit pas de pièces jointes propres à une demande : ne pas afficher de téléversement fictif.
- Une demande peut être créée sans adresse, mais l'acceptation du devis ne peut pas créer la commande tant qu'une adresse du client n'est pas sélectionnée. L'interface de devis bloque l'acceptation dans ce cas et renvoie vers le détail de la demande pour corriger l'adresse.
- La messagerie possède déjà un contexte optionnel de demande. La page propose la conversation existante autorisée lorsqu'elle correspond au client et au professionnel ; sinon elle dirige vers la messagerie générale. La demande et la conversation restent des ressources séparées.
- L'historique de demande est un historique de statuts, complété par les journaux d'audit et l'historique de négociation du devis. Ne pas exposer les journaux administratifs ou des métadonnées privées dans la timeline client.
- Il n'existe pas d'expiration automatique de demande définie ici. Ne pas annuler ou refuser automatiquement un dossier sans règle métier approuvée.

## Validation attendue

Les tests de cette fonctionnalité doivent couvrir l'isolation client/professionnel, l'éligibilité du service, les filtres SQL, la pagination, l'idempotence, les transitions, l'historique, les notifications et la cohérence de l'intégration devis/commande. La CI doit exécuter la suite Laravel, le contrôle Pint, le build Vite et les migrations sur une base MySQL vierge.
