# Phase C06–C12 — Parcours des demandes client

## Objectif

Améliorer l'expérience des demandes client existantes sans reconstruire le module ni modifier les règles métier. Cette livraison touche uniquement les vues Blade, le CSS dédié et le JavaScript déjà chargé par les pages du parcours.

## Références UX consultées

- GOV.UK Design System — [Error summary](https://design-system.service.gov.uk/components/error-summary/) : résumé des erreurs, liens vers les champs et récupération sans effacer les valeurs saisies.
- GOV.UK Design System — [File upload](https://design-system.service.gov.uk/components/file-upload/) : attentes de format/taille explicites, feedback proche du champ et erreurs actionnables.
- DWP Design System — [File upload checklist](https://design-system.dwp.gov.uk/contribute/file-upload/summary) : rendre les limites visibles avant la sélection et penser aux connexions lentes, au mobile et aux cas limites.
- OWASP — [File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html) : le navigateur ne remplace jamais la validation du type réel, des permissions, de la taille et du stockage sûr.

Ces références inspirent la structure et les états d'interface ; aucun template externe n'est copié.

## Changements

### C06 — Liste des demandes

- Les demandes dont le statut requiert une action client (méthode requiresClientAction, actuellement les demandes avec devis) sont mises en évidence par un accent visuel et un message explicite.
- Les filtres de statut indiquent la page courante via aria-current.
- Les statuts, statistiques, filtres GET, tris, pagination, actions et autorisations existants sont conservés.

### C07/C09 — Création et modification du brouillon

- Les sections du formulaire disposent d'une progression visuelle numérotée, sans introduire de nouveau flux multi-étapes.
- Le résumé de service traduit les types de tarification connus en libellés métier.
- Les champs de description, budget et délai présentent des aides plus explicites.
- Les erreurs sont résumées au début de la page, liées aux champs correspondants et les erreurs de champs sont annoncées par les attributs ARIA.
- Les valeurs saisies et le mécanisme d'idempotence restent conservés.
- Le formulaire précise clairement qu'enregistrer ou modifier le brouillon ne transmet pas la demande au professionnel.
- Le choix d'adresse respecte la valeur précédemment soumise ; le client peut laisser le lieu à définir si le statut métier l'autorise. L'envoi exige toujours l'adresse vérifiée côté serveur lorsque le mode d'exécution l'impose.

### C08/C12 — Détail et cycle de vie

- La référence et la date de création sont plus visibles.
- Des notices contextuelles distinguent le brouillon non transmis d'une demande dont le devis attend une décision.
- Les confirmations de soumission et d'annulation rappellent l'effet de l'action.
- Les erreurs de validation du détail renvoient vers le formulaire ou l'action concernée.
- L'historique existant, les transitions, les routes et les actions affichées selon le statut sont préservés.

### C10 — Pièces jointes

- La limite de 10 Mo et les extensions prises en charge sont visibles près du sélecteur.
- Après sélection, le client voit le nom, l'extension, la taille et une miniature locale uniquement pour les images reconnues.
- Le navigateur affiche un message pour un fichier vide, trop volumineux ou d'extension non prise en charge. Ce contrôle est une aide UX uniquement : le serveur demeure autoritaire.
- Le bouton affiche un état « Envoi en cours… » sans inventer une progression chiffrée.
- La liste des fichiers existants, le téléchargement privé et les limites de statut/nombre de fichiers restent inchangés. Aucune suppression ni route nouvelle n'est introduite.

### C11 — Adresse de la demande

- Le choix « À définir » est explicite dans les formulaires, et les adresses physiques ne sont proposées que pour les prestations qui l'exigent.
- Le détail continue d'expliquer l'instantané de l'adresse et de réutiliser la sélection d'adresse existante.
- Les contrôles de propriété de l'adresse restent côté serveur.

## Audit backend en lecture seule

Fichiers examinés : ServiceRequestController, ServiceRequestAttachmentController, ServiceRequestService, ServiceRequestLifecycleService, les FormRequests de création/modification/adresse/pièce jointe, les routes web, ServiceRequestStatus et les vues/CSS/JS concernés.

Règles métier confirmées dans le code :
- la création enregistre un brouillon avec une clé d'idempotence ;
- la soumission est une action séparée et ne passe de brouillon à « demandée » qu'après validation du service et, si nécessaire, de l'adresse et de son instantané ;
- l'annulation est limitée aux statuts autorisés par le service de cycle de vie ;
- l'ajout de pièce jointe est limité à cinq fichiers, 10 Mo par fichier et aux types MIME/extensions définis par le FormRequest ;
- les fichiers sont enregistrés sur le disque privé local et le téléchargement vérifie l'accès et l'appartenance à la demande ;
- les adresses sont vérifiées côté serveur pour la propriété du client et le mode d'exécution.

Aucun contrôleur, service métier, modèle, FormRequest, enum, migration ou fichier de routes n'a été modifié dans cette phase.

## Validation

- Relecture statique des fichiers et des références aux routes effectuée via GitHub.
- Le build Vite, les tests Laravel/PHPUnit et les vérifications de rendu navigateur ne sont pas considérés comme réussis tant que le CI de cette branche ne les a pas exécutés.
- La prévisualisation d'image est locale au navigateur et ne remplace pas le contrôle serveur.
- Aucun merge n'est effectué par cette phase.
