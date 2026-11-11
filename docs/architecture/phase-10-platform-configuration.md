# Phase 10 — Configuration générale de PROXIWORK

## Objectif

La configuration admin est un centre de gouvernance, pas un CRUD libre de valeurs arbitraires. Les paramètres modifiables sont déclarés dans le code, typés, bornés et consommés par les services métier. Les secrets fournisseurs ne sont jamais enregistrés dans `platform_settings`.

## Règles transversales

- Une clé non déclarée dans le registre de paramètres est rejetée.
- Les valeurs sont validées côté serveur et normalisées avant persistance.
- Chaque modification est transactionnelle, versionnée par clé et auditée sans journaliser sa valeur.
- Les permissions de consultation et de modification sont distinctes.
- Le cache est invalidé après commit.
- Les paramètres ne peuvent jamais contourner les policies, les statuts métier, la modération, la vérification professionnelle ou les contrôles de paiement.
- Les écritures financières existantes sont immuables. Une nouvelle politique de commission doit être versionnée avec une date d'effet et un taux capturé pour chaque nouvelle opération ; modifier un taux courant ne doit pas recalculer un historique.
- Les secrets de paiement, e-mail, SMS, push et stockage restent dans l'environnement ou le gestionnaire de secrets.
- Les indicateurs d'intégration affichent une présence de configuration, pas un test de disponibilité fournisseur.

## Point 69 — Paramètres généraux

La table `platform_settings` stocke uniquement des valeurs opérationnelles non secrètes. Le registre `PlatformSettingService` est l'autorité de typage et d'autorisation des clés. Les changements sont atomiques et consignés dans le journal d'audit Phase 9.

## Point 70 — Devises

Un registre `currencies` contient USD et CDF, avec une devise principale unique et un état actif. Une devise ne peut pas être désactivée si elle est principale, utilisée par des services publiés, des demandes ouvertes, des commandes en attente de paiement ou des soldes de portefeuille. Les montants et devises historiques ne sont jamais convertis ou réécrits. Le registre ne prétend pas activer une capacité de paiement/retrait fournisseur non intégrée.

## Point 71 — Commissions

Le moteur existant calcule les montants en unités mineures entières et conserve le taux appliqué dans chaque commission comptabilisée. Le futur écran de gestion du taux doit utiliser des règles versionnées et capturer le taux applicable au moment de l'engagement commercial, avant paiement ; un taux global modifié après coup ne doit pas affecter une commande déjà engagée. Le simple paramètre `config('commission.rate')` ne doit donc pas être remplacé par une valeur mutable sans snapshot préalable.

## Point 72 — Vérification des professionnels

Le service `ProfessionalVerificationService` conserve les transitions PENDING → UNDER_REVIEW → VERIFIED/REJECTED, les verrouillages, l'historique des revues et l'audit. Les exigences documentaires globales configurables (identité, certificat, formation, licence) exigent un document de type correspondant au statut APPROVED avant la transition VERIFIED. Les exigences par catégorie restent un prolongement possible après inventaire métier. Aucun paramètre ne permet l'auto-vérification ou le contournement de la revue humaine.

## Point 73 — Publication des services

Les seuils de titre, description et nombre d'images sont configurables. Les règles existantes de statut du compte, e-mail vérifié, profil vérifié, modération CLEAR, catégorie active, tarification cohérente, couverture et compatibilité des compétences restent obligatoires.

## Point 74 — Notifications

Le service transactionnel existant garde la génération interne, la déduplication et les livraisons. Les bascules globales ne peuvent désactiver que les canaux facultatifs ; les événements critiques conservent leur traitement. Une panne fournisseur ne doit jamais annuler une commande ou un paiement.

## Point 75 — Médias

Les limites de taille deviennent configurables, mais les listes MIME autorisées et la séparation disques public/privé restent contrôlées par le code. Les documents sensibles ne doivent jamais être déplacés vers le disque public par un paramètre admin.

## Point 76 — Intégrations

Les états présentés sont des indicateurs de présence des paramètres. Les secrets ne sont ni lus dans les vues, ni retournés dans les réponses, ni modifiables depuis l'interface. Les webhooks doivent conserver leurs contrôles de signature, idempotence et cohérence montant/devise/commande.

## Limites à ne pas masquer

Le centre de configuration ne prétend pas activer des fonctionnalités fournisseur qui ne sont pas intégrées. Les règles de devises, commission versionnée, exigences documentaires par catégorie, tests actifs d'intégration, modèles de notification versionnés, politique de rétention des médias et nettoyage des fichiers orphelins nécessitent leurs lots métier dédiés et leurs tests de régression.
