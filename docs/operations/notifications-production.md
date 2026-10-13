# PROXIWORK — Mise en production des notifications (Point 24)

## 1. Préconditions

La plateforme conserve d'abord l'intention de notification et une ligne de livraison par canal. Un état SENT signifie que le transport a accepté la demande ; il ne prouve pas la livraison finale à la boîte mail, au téléphone ou à l'appareil. Les statuts SIMULATED, SKIPPED, FAILED et UNKNOWN ne doivent jamais être présentés comme une livraison réussie.

Ne jamais ajouter les secrets réels à .env.example, aux fichiers suivis par Git, aux captures d'écran ou aux journaux.

## 2. E-mail SMTP

Configurer MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION, MAIL_FROM_ADDRESS et MAIL_FROM_NAME dans le gestionnaire de secrets de l'environnement.

Pour Gmail, utiliser une adresse dédiée et un mot de passe d'application lorsque le compte le permet ; ne jamais utiliser le mot de passe principal du compte. Pour Brevo ou un autre fournisseur SMTP, utiliser les paramètres et identifiants SMTP fournis par ce fournisseur. Vérifier le domaine d'expédition, SPF, DKIM et DMARC avant l'ouverture au public.

Après toute modification des variables, recharger la configuration Laravel (php artisan config:cache au déploiement) et envoyer un message de test depuis un compte contrôlé. Vérifier la boîte de réception, les indésirables et les journaux du fournisseur.

## 3. File d'attente et planificateur

Utiliser une file persistante en production, par exemple QUEUE_CONNECTION=database ou Redis. Ne pas utiliser sync comme stratégie de production pour un volume réel.

Démarrer un worker supervisé et redémarrable :

    php artisan queue:work --sleep=3 --tries=3 --timeout=90

Configurer le cron du serveur pour exécuter le planificateur Laravel chaque minute :

    * * * * * cd /chemin/vers/PROXIWORK && php artisan schedule:run >> /dev/null 2>&1

La commande notifications:dispatch-pending sélectionne les livraisons arrivées à échéance. Surveiller la file, les lignes PENDING, RETRYING, FAILED et UNKNOWN, les erreurs de transport et les lignes PROCESSING trop anciennes. Les résultats UNKNOWN doivent être réconciliés avec le fournisseur avant tout nouvel envoi.

## 4. SMS Twilio

Le code prend en charge l'API Twilio Messages. Configurer les secrets TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_SMS_FROM et le code pays par défaut SMS_DEFAULT_COUNTRY_CODE dans l'environnement de production. L'authentification est faite par HTTP Basic ; les identifiants ne sont jamais ajoutés aux URL ou journaux.

Les numéros locaux commençant par zéro sont convertis au format international seulement selon le code pays configuré ; les numéros ambigus sont refusés. Pour la RDC, vérifier la couverture, les expéditeurs autorisés, les tarifs et les règles locales dans la console du fournisseur avant d'activer l'envoi réel. Aucun SMS n'est envoyé en environnement local ou de test : il est simulé. Un résultat réseau ambigu est marqué UNKNOWN pour éviter un renvoi automatique pouvant créer un doublon.

Une réponse HTTP acceptée est enregistrée comme SENT. Un webhook Twilio signé devra être ajouté si le produit exige un état DELIVERED fiable.

## 5. Push Firebase Cloud Messaging

Le code prend en charge Firebase Cloud Messaging HTTP v1 pour Android, iOS et clients Web capables de fournir un jeton FCM. Configurer FIREBASE_PROJECT_ID, FIREBASE_CLIENT_EMAIL et FIREBASE_PRIVATE_KEY dans le gestionnaire de secrets. Donner au compte de service uniquement les autorisations nécessaires à l'envoi FCM et renouveler/rotater ses clés selon la politique de sécurité.

Les clients authentifiés enregistrent leur jeton par POST /api/v1/notification-devices avec token et platform (android, ios ou web). À la déconnexion ou à la révocation d'un appareil, appeler DELETE /api/v1/notification-devices avec le jeton du compte connecté. Ne pas journaliser les jetons.

Les jetons FCM doivent être générés et renouvelés par le client mobile/Web via le SDK Firebase adapté à sa plateforme. Le backend ne fabrique pas les jetons d'appareil. Une réponse FCM acceptée est enregistrée comme SENT ; les jetons déclarés non enregistrés sont supprimés. Les résultats réseau ambigus deviennent UNKNOWN.

## 6. Préférences, confidentialité et SMS

Les préférences sont persistées côté serveur. Les alertes de sécurité critiques ne sont pas supprimées par les préférences facultatives e-mail/SMS/push. Les alertes de messagerie e-mail sont différées et abandonnées si la conversation est déjà lue avant l'échéance ; les alertes de message regroupées ne contiennent pas le texte intégral du message.

Le SMS est limité aux alertes critiques et à certains événements importants. Les canaux facultatifs peuvent être désactivés par l'utilisateur ; les alertes critiques conservent leur priorité. L'application ne doit pas prétendre qu'un SMS ou un push a été envoyé quand le statut est SIMULATED ou FAILED.

## 7. Liste de contrôle de déploiement

- [ ] Secrets injectés via le gestionnaire de secrets, jamais commités.
- [ ] Domaine d'e-mail authentifié (SPF/DKIM/DMARC) et test de réception réussi.
- [ ] Worker supervisé et cron du planificateur actifs.
- [ ] Tests d'envoi SMS effectués avec un numéro de test autorisé et budget validé.
- [ ] Couverture SMS RDC, coûts et expéditeur validés auprès du fournisseur.
- [ ] Projet Firebase et compte de service configurés ; appareil test enregistré et push reçu.
- [ ] Procédure de révocation des jetons d'appareil vérifiée.
- [ ] Alertes de surveillance et procédure de réconciliation des statuts UNKNOWN documentées.
- [ ] Test final réalisé sur l'environnement de préproduction avec les vrais fournisseurs.

Les derniers points dépendent de secrets et de comptes fournisseur propres au déploiement. Leur code est présent, mais une réception réelle ne peut être certifiée avant leur configuration et leur test sur l'environnement cible.
