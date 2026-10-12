# Notifications client — architecture et exploitation

## Source de vérité

- **notifications** est l'historique interne Laravel. Il est créé synchroniquement et n'est jamais désactivé par les préférences e-mail/SMS/push.
- **notification_deliveries** suit une tentative par canal (notification_id + channel unique). Elle ne remplace pas l'historique interne.
- **deduplication_key** est une clé SHA-256 nullable. Les événements métier doivent fournir une clé stable liée à l'identifiant de l'événement. Les URL stables servent de solution de repli quand elles existent.
- Les notifications et les intentions de livraison créées par TransactionalNotificationService sont enregistrées dans une transaction. Lorsqu'il est appelé à l'intérieur de la transaction métier, les deux écritures participent à cette transaction.
- DispatchPendingNotificationDeliveries relit les intentions durables et les met en file d'attente. Une panne du dispatch ne supprime donc pas l'intention enregistrée.

## États de livraison

| État | Signification |
| --- | --- |
| PENDING | Intention persistée, prête à être traitée. |
| PROCESSING | Un worker a pris en charge la tentative. |
| SENT | Le transport e-mail a accepté le message. Cela ne confirme ni la livraison finale ni la lecture. |
| DELIVERED | Réservé à une confirmation réelle d'un fournisseur. Aucun webhook de livraison n'est encore configuré. |
| RETRYING | Échec considéré comme réessayable ; un délai croissant est enregistré. |
| FAILED | Échec final connu. |
| UNKNOWN | La connexion a pu tomber après l'acceptation par le fournisseur. Pas de nouvel envoi automatique, afin d'éviter un doublon ; réconciliation requise. |
| SIMULATED | Le mailer log/array ou le simulateur SMS local a enregistré une simulation. Aucun vrai message n'a été envoyé. |
| SKIPPED | Le canal facultatif a été désactivé par le client avant l'envoi. |

Les erreurs stockent la classe technique, pas le corps du message ni les secrets. Les tentatives abandonnées en état PROCESSING sont marquées UNKNOWN après quinze minutes.

## Développement local

Le fichier .env.example utilise MAIL_MAILER=log et QUEUE_CONNECTION=sync. C'est volontaire : les e-mails sont simulés, et non présentés comme réellement livrés.

Pour vérifier les intentions et exécuter le dispatch :

~~~bash
php artisan migrate
php artisan notifications:dispatch-pending
~~~

Pour tester une vraie file de travaux locale, configurer QUEUE_CONNECTION=database, puis lancer un worker dans un terminal séparé :

~~~bash
php artisan queue:work --tries=5
~~~

Pour faire tourner le planificateur localement :

~~~bash
php artisan schedule:work
~~~

En production, un worker de queue doit être surveillé par Supervisor/systemd ou le mécanisme du fournisseur, et le planificateur Laravel doit être appelé chaque minute (php artisan schedule:run). La commande de dispatch est nécessaire pour reprendre les intentions restées en attente.

## Configurer un transport e-mail réel

Ne pas committer les secrets. Définir les variables dans l'environnement protégé du serveur, puis exécuter php artisan config:cache après configuration.

Exemple Gmail SMTP (un compte Gmail peut imposer la validation en deux étapes et un mot de passe d'application) :

~~~dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=adresse-expediteur@example.com
MAIL_PASSWORD=secret-fourni-hors-du-depot
MAIL_FROM_ADDRESS=adresse-expediteur@example.com
MAIL_FROM_NAME="PROXIWORK"
~~~

Exemple de paramètres SMTP Brevo (utiliser les identifiants SMTP fournis par Brevo et une adresse d'expéditeur validée) :

~~~dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=identifiant-smtp-fourni-par-brevo
MAIL_PASSWORD=cle-smtp-fournie-par-brevo
MAIL_FROM_ADDRESS=notifications@votre-domaine.example
MAIL_FROM_NAME="PROXIWORK"
~~~

Ces exemples ne prouvent pas que le compte est configuré ni que les messages seront livrés. En production, vérifier le domaine d'envoi, SPF, DKIM et DMARC, les quotas, les rebonds et les journaux du fournisseur. Un statut SENT signifie seulement que le transport a accepté le message.

## SMS et push

En environnement local/testing, les alertes éligibles peuvent créer une ligne SMS SIMULATED avec un numéro masqué ; aucun SMS réel n'est envoyé. En production, aucun fournisseur SMS ou push n'est connecté par cette tranche. Ne pas annoncer qu'un SMS ou une notification push a été envoyé. Avant production, choisir un fournisseur qui prend effectivement en charge les numéros RDC, ajouter la gestion du consentement, les coûts/quotas et les retours de livraison. FCM peut être évalué séparément pour les appareils Web/mobile compatibles.

## Points opérationnels

- Protéger les tables de livraison et les diagnostics derrière des rôles administratifs autorisés.
- Surveiller PENDING, RETRYING, FAILED et UNKNOWN ; ne pas relancer automatiquement un résultat incertain.
- Ne pas désactiver la création des notifications internes lorsqu'un canal externe échoue.
- Les notifications transactionnelles sont générées par les services métier. Pour garantir l'atomicité, l'appel au service doit rester dans la même transaction que le changement métier ; les nouvelles intégrations doivent suivre ce modèle.
