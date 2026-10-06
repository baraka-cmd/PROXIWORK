# ProfessionalProfile — conception métier et données

## 1. Responsabilité

`professional_profiles` représente l’identité professionnelle d’un utilisateur qui souhaite proposer ses compétences sur PROXIWORK.

Il ne remplace ni `users`, ni `profiles`, ni `addresses`.
- `users` : identité technique et compte.
- `profiles` : identité personnelle et préférences générales.
- `addresses` : adresses personnelles de l’utilisateur.
- `professional_profiles` : identité, présentation et état professionnel visibles dans le marketplace.

Relation cible : User → Profile (1), ProfessionalProfile (0..1), Addresses (0..N).

Un compte peut donc être créé comme client sans posséder immédiatement de ProfessionalProfile.

## 2. Pourquoi une table séparée

Ne pas ajouter profession, expérience, statut, vérification et localisation professionnelle dans `profiles`.

Cela permet de garder `profiles` stable et générique, de créer le profil professionnel seulement lorsqu’il est nécessaire, d’appliquer des autorisations spécifiques et de faire évoluer le marketplace sans gonfler le profil personnel.

## 3. Structure proposée

| Champ | Type cible | Obligatoire | Rôle |
|---|---|---:|---|
| `id` | BIGINT | oui | Identifiant |
| `user_id` | FK unique | oui | Propriétaire |
| `professional_title` | VARCHAR(150) | oui | Titre professionnel |
| `description` | TEXT | non | Présentation |
| `years_experience` | UNSIGNED SMALLINT | non | Expérience déclarée |
| `starting_price` | DECIMAL(12,2) | non | Tarif indicatif |
| `currency` | CHAR(3) | non | Devise ISO 4217 |
| `province` | VARCHAR(100) | non | Zone professionnelle |
| `city` | VARCHAR(100) | oui | Ville principale |
| `commune` | VARCHAR(100) | non | Commune/secteur |
| `service_radius_km` | UNSIGNED SMALLINT | non | Rayon indicatif |
| `latitude` | DECIMAL(10,7) | non | Point professionnel approximatif |
| `longitude` | DECIMAL(10,7) | non | Point professionnel approximatif |
| `status` | état applicatif | oui | État du profil |
| `visibility` | état applicatif | oui | Visibilité marketplace |
| `verification_status` | état applicatif | oui | État de vérification |
| `verified_at` | TIMESTAMP nullable | non | Date de vérification |
| `created_at` / `updated_at` | TIMESTAMP | oui | Audit technique |

## 4. Valeurs métier

### status
- `draft` : préparation.
- `active` : profil utilisable.
- `suspended` : suspension temporaire.
- `closed` : profil fermé.

Un profil n’est pas automatiquement `active` à sa création.

### visibility
- `public` : visible dans la recherche publique.
- `private` : non publié.

`visibility` et `status` restent séparés : un profil peut être `active` mais `private`.

### verification_status
- `unverified`
- `pending`
- `verified`
- `rejected`

Le processus documentaire complet sera traité plus tard ; cette colonne représente seulement l’état courant.

## 5. Tarification

Le profil ne devient pas le catalogue tarifaire du professionnel.
`starting_price` est uniquement un prix indicatif.
Les prix réels appartiendront ensuite à `services`, `quotes`, `orders` et `order_items`.

## 6. Localisation

Ne pas réutiliser directement `addresses`. Une adresse personnelle peut être confidentielle ou destinée à une livraison.

La localisation professionnelle représente une zone de recherche marketplace, pas nécessairement une adresse postale exacte.

Les coordonnées GPS sont optionnelles et ne devront pas exposer inutilement une adresse privée.

## 7. Contraintes

- `user_id` unique et FK avec cascade.
- `professional_title` obligatoire et borné.
- `city` obligatoire.
- `years_experience` non négatif.
- `starting_price` non négatif.
- `currency` validée et normalisée en majuscules.
- latitude et longitude ensemble ou aucune.
- latitude entre -90 et 90.
- longitude entre -180 et 180.
- `service_radius_km` positif lorsqu’il est renseigné.
- `verified_at` cohérent avec l’état de vérification.
- les états sensibles ne sont pas librement modifiables par le professionnel.

## 8. Index

- unique `user_id`.
- `(status, visibility)` pour les profils publiables.
- `(city, status, visibility)` pour la découverte locale.
- index sur `verification_status` seulement si les requêtes administratives le justifient.

Pas d’index géospatial prématuré : il sera décidé avec le vrai moteur de recherche.

## 9. Autorisation

Le propriétaire pourra créer, consulter et modifier ses données éditoriales.

Il ne pourra pas modifier directement `verification_status` ni `verified_at`. Les transitions administratives seront réservées aux rôles autorisés.

La publication sera contrôlée par une règle métier cohérente avec le statut.

## 10. Confidentialité

Une ressource publique ne doit pas exposer automatiquement `user_id`, les coordonnées GPS exactes, les informations internes de vérification ou les données d’audit.

Nous prévoyons à terme une ressource propriétaire, une ressource publique et éventuellement une ressource administrative.

## 11. Historique

Le ProfessionalProfile est mutable et ne doit jamais être la source historique d’une commande.

Les informations nécessaires à une transaction devront être snapshotées dans les structures de commande.

## 12. Hors périmètre

Ne pas ajouter maintenant : catégories, compétences détaillées, services, images de services, portfolio, disponibilité détaillée, avis, statistiques, documents de vérification, moyens de paiement, portefeuille ou messagerie.

Ces éléments auront leurs propres modules.

## 13. Décision

ProfessionalProfile reste une table de présentation et d’état professionnel, pas une table fourre-tout.

Architecture : User → Profile + ProfessionalProfile + Addresses. Puis ProfessionalProfile → Categories/Skills, Services, Portfolio, Availability, Reviews et Orders.

Cette séparation prépare le marketplace sans créer de dette structurelle prématurée.