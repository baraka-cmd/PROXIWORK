# Professional Search — 4.4 / 4.5

## Objectif

Le moteur recherche des ProfessionalProfile publics et ne retourne que les professionnels possédant au moins un service publié dans une catégorie active.

## Contrat

Endpoint: GET /api/v1/professionals

Filtres:
- profession: titre professionnel.
- search: recherche libre sur titre, bio, nom, services et compétences.
- category: slug de catégorie active.
- skill: slug d'une compétence.
- skills[]: plusieurs compétences.
- skills_mode=any|all: OR ou AND pour skills[].
- city, province: recherche sur les adresses du professionnel sans exposer l'adresse exacte.
- min_price, max_price, currency: recherche sur les offres publiées.
- rating: note minimale.
- availability: unknown|available|unavailable.
- verification: pending|under_review|verified|rejected.
- sort: relevance|rating|price_low|price_high|newest.
- per_page: 1 à 100.
- page: pagination Laravel standard.

## Données de découverte

professional_profiles possède: professional_title, verification_status, availability_status, rating_average, rating_count.

rating_average et rating_count sont des agrégats de lecture. Le futur module Reviews sera responsable de leur maintien; le moteur de recherche ne recalcule pas les AVG à chaque requête.

verification_status sera piloté par le futur workflow de vérification et ne doit jamais être considéré comme équivalent à la vérification d'e-mail.

availability_status est un indicateur V1. Le futur module de disponibilités pourra fournir un calendrier plus précis sans changer le contrat de recherche.

## Sécurité

- Aucun user_id n'est accepté depuis le client pour la recherche.
- Aucun email, mot de passe, token, téléphone privé, coordonnées GPS ou adresse exacte n'est exposé.
- Les colonnes de tri sont strictement whitelistées.
- Les filtres sont validés par ProfessionalSearchRequest.
- Les recherches relationnelles utilisent whereHas et les paramètres restent bindés par Eloquent/Query Builder.
- Les résultats sont limités par pagination.

## Performance

- Eager loading explicite des relations consommées par la Resource.
- withCount pour le nombre de services publiés.
- Index ciblés sur les attributs de découverte.
- Pas d'Elasticsearch/Meilisearch en V1 avant mesure de volumétrie réelle.
- Un test protège le contrat N+1 de base.

## Évolution

- V2: recherche géographique par rayon.
- V2: full-text / moteur dédié si la volumétrie le justifie.
- V2: classement pondéré plus sophistiqué.
- V2: disponibilité calculée à partir de créneaux.
- V2: score de réputation issu des reviews.