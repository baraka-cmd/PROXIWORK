# P10 — Profil et adresses client (C30–C38)

## Objectif et périmètre

Moderniser l’interface du profil client, de ses paramètres de sécurité et du carnet d’adresses sans reconstruire les parcours Laravel existants. Cette livraison reste limitée aux vues Blade, aux feuilles de style, au JavaScript d’interface et à cette documentation.

## Audit backend effectué en lecture seule

Les fichiers et parcours examinés sur `audit/current-project-baseline` comprennent :

- `routes/web.php` : routes de consultation/modification du profil, photo privée, changement de mot de passe, demande/confirmation d’e-mail et gestion des adresses.
- `ProfileController`, `UpdateClientProfileRequest`, `UpdateClientPasswordRequest` et `RequestClientEmailChange`.
- `AddressController`, `AddressService` et `AddressPolicy`.
- Les vues Blade du profil et des adresses, le layout client, les CSS/JS associés et le mécanisme de confirmation partagé.

Contraintes métier conservées :

- L’identité, les rôles, les états du compte et la vérification ne sont pas rendus modifiables par des champs de présentation.
- Le changement d’e-mail reste un parcours avec mot de passe actuel, lien de confirmation, échéance et révocation des autres sessions/jetons après confirmation.
- Le changement de mot de passe garde les règles serveur de complexité et la révocation des autres sessions/jetons.
- La photo reste validée par le serveur (JPEG/PNG/WebP, 2 Mo maximum, dimensions de 2 000 × 2 000 pixels maximum), stockée sur le disque privé et servie par la route authentifiée. Les contrôles JavaScript ne remplacent jamais la validation serveur.
- Les adresses restent privées et autorisées par la policy. Les opérations POST/DELETE, CSRF, le service transactionnel, l’unicité de l’adresse par défaut et les règles de suppression restent inchangés.
- Les coordonnées GPS sont facultatives, mais le backend exige la latitude et la longitude ensemble.
- Les modifications du carnet ne réécrivent pas les instantanés d’adresses historiques des demandes/commandes.
- Aucune route, migration, table, règle métier, policy, FormRequest ou méthode de contrôleur n’a été modifiée.

## Changements UI

### C30 — Consultation du profil

- Hiérarchie renforcée pour la photo/les initiales, le nom, le rôle client, l’état de vérification de l’e-mail et la date d’inscription.
- Initiales dérivées du nom disponible, sans inventer de statut de confiance ni de pourcentage de complétion.
- Explication de vérification plus courte, qui distingue explicitement l’accès à l’e-mail de l’identité civile.
- Raccourcis de compte conservés et mieux adaptés aux petits écrans.

### C31 — Paramètres du profil

- Navigation par ancres vers informations personnelles, photo, adresse e-mail et mot de passe.
- Résumé d’erreurs accessible avec liens vers les champs et focus placé sur le résumé après retour serveur.
- Les formulaires indépendants et leurs routes sont conservés. Les champs sensibles ne sont pas préremplis.
- États de soumission limités au formulaire concerné.
- Les erreurs de changement d’e-mail pointent vers le champ de nouvelle adresse, pas vers l’e-mail affiché en lecture seule.

### C32 — Photo

- Présentation distincte de la photo actuelle et de la nouvelle sélection.
- Aperçu local avec nom de fichier et validation indicative du type, de la taille et des dimensions.
- Erreurs annoncées et reliées au champ. L’aperçu temporaire est libéré lors du remplacement et du départ de page.
- Suppression confirmée via le dialogue partagé, avec indication que les autres données du profil restent intactes.

### C33 — Mot de passe

- Champs séparés visuellement, avec contrôles afficher/masquer accessibles.
- Règles de complexité et conséquences de révocation toujours visibles.
- Aucun mot de passe n’est conservé par le JavaScript, ajouté aux logs ou affiché après soumission.

### C34 — Changement d’e-mail

- État d’attente et échéance plus lisibles ; distinction entre une demande en attente et une demande expirée.
- Page de confirmation qui détaille les conséquences et rappelle que le parcours requiert une session authentifiée.
- La page d’erreur 4xx existante reconnaît uniquement le message métier de lien e-mail invalide/expiré et propose un retour direct aux paramètres e-mail ; les autres erreurs conservent le rendu générique.
- Le jeton n’est jamais affiché dans le texte de la page.

### C35–C38 — Adresses

- Liste conservée en cartes responsives avec badge explicite de l’adresse par défaut, détails facultatifs conditionnels, actions lisibles et pagination existante.
- Confirmation de suppression corrigée pour utiliser le dialogue partagé réellement pris en charge, en nommant l’adresse et en rappelant la conservation de l’historique.
- Formulaire organisé en groupes : identification, adresse/localisation, contact/instructions, GPS facultatif et préférences.
- Retrait de `novalidate` afin de rétablir les contrôles HTML natifs simples, tout en gardant Laravel comme autorité.
- Résumé d’erreurs avec liens, attributs `aria-invalid`/`aria-describedby`, focus visible et état de soumission.
- Aucune carte obligatoire, géolocalisation automatique ou donnée d’adresse publique ajoutée.

## Références UX consultées

- Dribbble — inspirations de pages de paramètres de compte : https://dribbble.com/search/account-settings-profile-page et https://dribbble.com/search/account-settings-page
- Baymard Institute — recherche sur les parcours de compte et d’auto-service : https://baymard.com/research-articles/current-state-accounts-selfservice
- Baymard Institute — exemples et constats UX sur les adresses enregistrées : https://baymard.com/ecommerce-design-examples/59-address-book
- GOV.UK Design System — résumé des erreurs : https://design-system.service.gov.uk/components/error-summary/
- GOV.UK Design System — messages d’erreur : https://design-system.service.gov.uk/components/error-message/
- Home Office User-Centred Design Manual — prévention et récupération après erreur : https://design.homeoffice.gov.uk/accessibility/interactivity/error-messages

Les références visuelles ont servi d’inspiration de hiérarchie et de structure, pas de composants à copier. Les principes retenus sont la clarté des actions, l’accès direct aux réglages courants, les erreurs reliées aux champs, la conservation des saisies et la lisibilité mobile.

## Fichiers UI concernés

- `resources/views/client/profile/show.blade.php`
- `resources/views/client/profile/edit.blade.php`
- `resources/views/client/profile/email-change-confirmation.blade.php`
- `resources/views/client/addresses/index.blade.php`
- `resources/views/client/addresses/form.blade.php`
- `resources/views/errors/4xx.blade.php` (affichage contextualisé uniquement pour le lien de confirmation d’e-mail expiré/invalide ; les autres erreurs 4xx gardent leur présentation générique)
- `resources/css/pages/client/profile.css`
- `resources/css/pages/client/addresses.css`
- `resources/js/pages/client/profile.js`
- `resources/js/pages/client/addresses.js`

## Vérification et limites

- L’audit backend était en lecture seule ; aucune modification backend n’a été faite.
- Aucun test n’a été exécuté dans un navigateur pendant l’implémentation via GitHub. L’affichage réel, le parcours de photo, la navigation clavier, le lecteur d’écran, les erreurs de validation, le mobile et la confirmation/suppression restent à vérifier manuellement.
- Le build Vite, les tests Laravel, les tests Feature des adresses/profil et les migrations doivent être évalués par la CI déclenchée sur la PR. Les résultats seront rapportés seulement après consultation effective de cette exécution.
- Cette branche est proposée en PR ; elle ne doit pas être fusionnée sans autorisation explicite.
