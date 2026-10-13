# PROXIWORK — Messagerie professionnelle (Point 25)

## Objectif et frontières métier

La messagerie permet au client et au professionnel autorisés d'échanger au sujet d'une prestation. Elle ne remplace pas les modules officiels de demandes, devis, commandes ou paiements : une phrase dans un message ne déclenche aucune acceptation commerciale.

Les conversations de la première version sont individuelles. Les appels audio/vidéo, groupes, indicateurs de frappe et WebSockets ne sont pas activés. Le navigateur utilise une synchronisation périodique toutes les huit secondes lorsque l'onglet est visible. Les messages sont persistés côté serveur avant que l'interface les considère comme envoyés.

## Modèle et regroupement

- `conversations.context_key` distingue une discussion générale, une demande (`request:{id}`) ou une commande (`order:{id}`).
- Une contrainte unique protège le regroupement par type, client, professionnel et contexte.
- Les anciennes conversations sont migrées vers leur contexte existant avant l'ajout de la contrainte.
- `conversation_participants` conserve les marqueurs de lecture, l'épinglage et l'archivage propres à chaque participant.
- `messages.idempotency_key` est une clé UUID fournie par le client. Elle évite un doublon en cas de nouvelle tentative avec la même clé. Le même texte peut être envoyé à nouveau avec une nouvelle clé.
- `message_attachments` conserve uniquement les métadonnées et un chemin généré côté serveur. Les fichiers sont stockés sur le disque privé `local`, jamais servis depuis une URL publique.

Les migrations ne suppriment pas les conversations ni les messages existants. Un retour arrière qui supprimerait les contextes multiples ne doit pas être exécuté sans une procédure de sauvegarde et de migration de données dédiée.

## Règles d'accès et de sécurité

1. Chaque lecture, envoi, accusé de lecture, changement d'état, signalement et téléchargement vérifie l'appartenance de l'utilisateur à la conversation.
2. Le serveur fixe l'auteur et l'horodatage. Le navigateur ne décide jamais qu'un message est lu.
3. Les messages sont rendus comme texte échappé. Les nouveaux messages sont ajoutés au DOM avec `textContent`, jamais avec du HTML fourni par l'utilisateur.
4. Les pièces jointes acceptées sont JPG/JPEG, PNG, WebP, PDF, DOC et DOCX ; maximum cinq fichiers par message et 10 Mo par fichier. Le MIME est contrôlé côté serveur. Le téléchargement exige une session ou un jeton API et une autorisation de participant ; la réponse est forcée en téléchargement avec `nosniff`.
5. Les limites de fréquence s'appliquent à l'envoi, au signalement, au blocage, à l'archivage et aux accusés de lecture.
6. Un signalement crée un enregistrement dans le système de modération existant. Un blocage ferme la conversation pour les deux participants, sans supprimer son historique.
7. L'archivage et l'épinglage sont des préférences du participant. Un nouveau message entrant restaure la conversation archivée dans sa boîte de réception.
8. Il n'existe pas de suppression définitive ou de modification de message dans cette version : cela protège la cohérence des échanges utiles aux litiges.

## Statuts d'envoi et de lecture

- `sent` : le serveur a enregistré le message durablement.
- `read` : le marqueur de lecture serveur du destinataire couvre l'identifiant du message.
- `received` dans la ressource API désigne un message écrit par l'autre participant ; ce n'est pas une preuve de livraison sur un appareil.
- Il n'y a pas de statut `delivered` : aucun accusé de livraison fiable au niveau appareil n'est actuellement configuré.

La lecture est enregistrée à l'ouverture de la conversation et synchronisée pendant le polling. Chaque participant peut désactiver le partage des accusés de lecture pour une conversation ; le marqueur de lecture reste conservé côté serveur pour le compteur de non-lus, mais il n'est pas révélé à l'autre participant tant que le partage est désactivé. Le polling est suspendu quand l'onglet est masqué. En cas de coupure réseau, l'interface affiche un état de synchronisation et le texte non envoyé reste dans le formulaire jusqu'à la navigation ou l'actualisation.

## Notifications des messages

Chaque message est persisté avant la création de l'alerte. Une notification interne récente et non lue est réutilisée pendant une fenêtre de deux minutes afin de limiter les alertes répétitives. L'e-mail est différé de deux minutes et le job vérifie que la conversation contient encore un message non lu avant d'envoyer l'alerte. L'e-mail contient une alerte générique, pas le texte complet de la conversation.

Les e-mails restent dépendants d'un fournisseur configuré et de workers/planificateur actifs. L'acceptation SMTP n'est pas une preuve de livraison en boîte de réception.

## Configuration opérationnelle

- Le disque `local` doit rester privé. Ne pas remplacer les liens de téléchargement autorisés par `Storage::url()` ou des URL publiques.
- Exécuter les workers Laravel et le planificateur selon le runbook notifications.
- Configurer un stockage durable pour les fichiers en production et prévoir sauvegarde, rétention et surveillance de capacité.
- Ajouter un antivirus côté serveur lorsque l'infrastructure de déploiement le permet. En attendant, les formats exécutables sont refusés et les téléchargements sont forcés en pièces jointes.
- Ne pas annoncer un chiffrement de bout en bout : cette propriété n'est pas implémentée.

## Vérifications automatisées prévues

La suite couvre les permissions, l'idempotence, la séparation des contextes, les pièces jointes privées, les accusés de lecture, l'archivage/épinglage, le blocage et le signalement. La CI doit confirmer le résultat final sur le dernier commit de la branche avant fusion.
