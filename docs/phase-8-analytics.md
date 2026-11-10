# Phase 8 — Analytics avancées et rapports

## Périmètre livré dans cette première intégration

Cette phase enrichit le service AdminAnalyticsService déjà présent. Elle ne crée pas de deuxième entrepôt de données et ne modifie aucune opération métier.

- **57 — Demandes et commandes :** volumes de demandes hors brouillons, demandes ayant un devis ou une commande, états, volumes de devis, acceptation, commandes créées/terminées, taux descriptifs et durée moyenne entre acceptation et achèvement.
- **58 — Services et catégories :** services publiés, services actuellement éligibles à une demande selon le scope métier requestable, professionnels publiquement visibles ayant au moins un service éligible, demandes et commandes par catégorie directe.
- **59 — Revenus et commissions :** évolution des transactions réussies, commissions comptabilisées et remboursements réussis, toujours séparés par devise.
- **60 — Géographie :** répartition de l’offre professionnelle et de son activité par province/ville, à partir de la localisation de base publique du professionnel.
- **61 — Rapports :** export CSV agrégé, limité à la permission analytics.export, avec période, horodatage, encodage UTF-8, téléchargement privé/no-store et protection contre l’injection de formules dans les tableurs.

## Définitions et règles métier

### Demandes, devis et commandes

- Les demandes draft sont exclues des volumes commerciaux.
- Les demandes sont attribuées à la période par service_requests.created_at.
- Les devis sont attribués à la période par quotations.created_at; un devis accepté est identifié par son état accepted.
- Les commandes créées sont attribuées par orders.created_at.
- Les commandes terminées sont attribuées par orders.completed_at, pas par leur date de création.
- La durée moyenne est calculée entre accepted_at et completed_at, uniquement lorsque les deux dates existent.
- Les taux sont des indicateurs descriptifs de période et ne doivent pas être interprétés comme une cohorte parfaitement mature ni comme une causalité. Une analyse de cohorte événementielle nécessitera un historique d’événements métier complet et une définition temporelle distincte.

### Catalogue

- published_services est le nombre de services au statut publié.
- requestable_services applique le scope existant Service::requestable() : publication, modération claire, catégorie active, professionnel publiquement visible et cohérence des compétences.
- Les statistiques de catégorie sont directes : elles ne réadditionnent pas automatiquement les sous-catégories dans le parent.
- Les vues ne sont pas affichées : le système ne dispose pas encore d’une télémétrie de consultation validée. Aucun volume de vues n’est inventé.

### Finances

- Les paiements agrégés proviennent uniquement des transactions succeeded ayant processed_at.
- Les commissions sont agrégées uniquement au statut posted, à partir de posted_at.
- Les remboursements sont agrégés uniquement au statut succeeded, à partir de processed_at.
- Aucune somme n’additionne plusieurs devises. Chaque devise constitue une ligne/série séparée.
- Les analytics sont en lecture seule : aucune écriture, correction, recalcul ou mutation de portefeuille, paiement, commission ou remboursement n’est déclenchée.

### Géographie et confidentialité

- La géographie utilise professional_profiles.province et professional_profiles.city pour les profils répondant au scope publiclyVisible().
- Les adresses privées des clients, les snapshots d’adresse et les coordonnées GPS exactes ne sont ni interrogés ni exportés.
- Les groupes de moins de trois professionnels sont omis entièrement du tableau et de l’export.
- Les zones sont des localisations déclarées par les professionnels, pas une preuve du lieu d’exécution effectif d’une prestation.

### Accès et export

- La consultation du tableau reste protégée par admin.dashboard.view.
- L’export est protégé séparément par analytics.export et limité en fréquence.
- Le CSV est agrégé : il ne contient ni identifiants individuels, ni coordonnées, ni adresses client, ni lignes de portefeuille.
- Les valeurs susceptibles de commencer par =, +, -, @, tabulation ou retour chariot sont neutralisées avant écriture CSV.

## Limites explicites — travaux restant à valider/compléter

1. Comparaisons de période : ajouter une période précédente de même durée, avec règles claires pour les périodes incomplètes.
2. Cohortes commerciales fiables : suivre une demande créée pendant la période jusqu'à sa conversion ultérieure, sans confondre date de création et date d'événement.
3. Télémétrie catalogue : instrumenter les vues avec déduplication, exclusion des robots, limitation de débit et politique de rétention avant d'afficher des statistiques de consultation.
4. Géographie de la prestation : ajouter une dimension de lieu d'exécution uniquement si un snapshot métier fiable et suffisamment normalisé existe; ne jamais substituer une adresse client privée à la localisation professionnelle publique.
5. Rapports XLSX/PDF et historique : ajouter après inventaire des dépendances déjà installées, stockage privé, expiration, journal d'audit, statut de génération et permissions dédiées.
6. Traitement asynchrone : réserver les jobs en file aux exports volumineux, avec idempotence, reprise, nettoyage des fichiers et contrôle d'accès au téléchargement.
7. Performance : analyser les plans SQL et les index sur une base représentative avant toute migration d'index ou stratégie de cache.

## Validation nécessaire avant fusion

Exécuter au minimum :

- php artisan test --filter=AnalyticsControllerTest
- php artisan test --filter=AdminDashboardTest
- php artisan route:list --name=admin.analytics
- php artisan migrate:fresh --seed sur une base de test isolée
- npm run build

Contrôler également les données réelles pour confirmer les définitions de statut, les volumes par devise, les catégories sans demande, les zones sous le seuil de confidentialité et les périodes sans données. Ne pas fusionner tant que les tests ne sont pas exécutés dans un environnement Laravel.
