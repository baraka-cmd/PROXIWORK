# PROXIWORK

**Plateforme de mise en relation Clients ↔ Professionnels**

> Trouvez le bon professionnel près de chez vous.

## Vision

PROXIWORK est une plateforme marketplace conçue pour permettre aux clients de découvrir des professionnels, consulter leurs services, demander des prestations, recevoir et négocier des devis, commander des services, effectuer des paiements et évaluer les prestations réalisées.

Le projet est construit avec une architecture Laravel moderne, orientée API REST, sécurité, maintenabilité, testabilité et évolution progressive.

## Architecture cible

- Laravel 13
- PHP 8.3+
- MySQL
- Laravel Sanctum pour l'authentification API
- Architecture MVC enrichie par Services, Policies, Form Requests et API Resources
- Tests automatisés
- GitHub Actions pour l'intégration continue
- Docker prévu dans la phase de production

## Phases

1. Foundation & Core Platform
2. Marketplace & Professional Discovery
3. Transactions, Quotes, Orders & Payments
4. Administration, Scale, Analytics & Production

## Principes de développement

- Code lisible et explicite
- Responsabilités séparées
- Validation systématique des entrées
- Autorisation centralisée
- Réponses API cohérentes
- Tests des comportements critiques
- Aucun secret dans Git
- Évolution sans casser les contrats API existants
