# Audit technique — Annuaire des professionnels

**Branche auditée :** `release/proxiwork-final-delivery`  
**Date :** 10 octobre 2026  
**Périmètre :** routes web et API publiques, modèle professionnel, publication, vérification, avis, favoris, recherche, vues, ressources, migrations et tests.

## Conclusion

L’annuaire existe déjà et s’appuie sur les modèles, services, vues et règles de sécurité de la marketplace. Il ne faut pas créer un second catalogue ni fusionner aveuglément d’anciennes implémentations. Les contrôles de visibilité sont appliqués dans les requêtes serveur, pas uniquement dans Blade.

La CI consultée pour le commit `de83d997b7fe387eb8941e9ec84b334e79db2b75` est verte : jobs **Fresh MySQL migration** et **Laravel tests** réussis. Ce résultat valide ce commit uniquement; toute modification ultérieure devra être revalidée.

## Résultats par domaine

### 1. Routes et contrôleurs — présent

- Web : `GET /professionals` vers `PublicSearchController::professionals`.
- Web : `GET /professionals/{professionalProfile}/{slug?}` vers `PublicProfessionalController::show`.
- API : `GET /api/v1/professionals` vers `ProfessionalSearchController::index`.
- Les routes publiques sont limitées par `throttle:api`.
- La recherche des professionnels reste séparée de la recherche de services, tout en réutilisant les mêmes données de la marketplace.

### 2. Modèle professionnel et publication — présent

- Le modèle est `ProfessionalProfile`, lié à `User`, aux services, compétences, favoris, avis et documents de vérification.
- Le scope `publiclyDiscoverable()` exige un profil actif, une visibilité publique, un compte utilisateur actif et au moins un service publié associé à une catégorie active.
- La fiche publique réapplique ce scope et répond en 404 si le profil n’est pas admissible.
- Les adresses personnelles ne sont pas utilisées pour le filtre géographique public : celui-ci s’appuie sur la zone professionnelle déclarée.

### 3. Vérification et modération — présent

- Les statuts de vérification proviennent de `ProfessionalVerificationStatus`.
- Le badge public n’est affiché que lorsque le statut est `verified` **et** que `verified_at` est renseigné.
- Les états internes de vérification ne sont pas exposés par la ressource de recherche.
- L’éligibilité à la publication dépend de la visibilité, du statut du profil, du compte et des services publiés; la vérification n’est pas simulée par un abonnement.

### 4. Abonnements, paiements et promotions — non constatés dans le périmètre audité

- Les modèles et migrations consultés montrent le système de paiements transactionnels, mais aucun modèle ou schéma d’abonnement professionnel ou de promotion payante n’a été identifié dans les emplacements examinés.
- En conséquence, l’annuaire ne doit pas inventer des forfaits, un statut « sponsorisé » ou des avantages payants.
- Si un système d’abonnement/promotion est ajouté plus tard, il devra avoir son propre état vérifiable côté serveur, une période de validité, une source de paiement confirmée, une traçabilité et une étiquette explicite. Il ne pourra jamais remplacer les règles d’approbation, de suspension ou de publication.

### 5. Notes et avis — mécanisme existant, cohérence à préserver

- Les avis sont liés à une commande unique; le service de création exige une commande terminée et son propriétaire.
- La modération des avis recalcule les agrégats du profil; les avis masqués ne doivent pas contribuer aux notes publiques.
- La fiche publique calcule sa synthèse depuis les avis publiés ayant une date de publication.
- Les cartes et le classement utilisent les agrégats conservés sur le profil. Ces agrégats doivent rester synchronisés par le service de domaine lors de toute création/modération d’avis. Toute nouvelle voie de modification d’avis doit impérativement passer par ce service ou recalculer ces agrégats.

### 6. Favoris — présent

- Les routes web et API exigent l’authentification et le rôle client.
- La policy vérifie le rôle, interdit de se favoriser soi-même et exige que le profil cible soit publiquement admissible.
- La suppression vérifie la propriété du favori; l’index des favoris exclut les professionnels devenus invisibles.
- Les formulaires web utilisent les méthodes HTTP attendues et le jeton CSRF.
- Le lien de connexion invité conserve uniquement une URL publique de profil professionnel comme destination de retour; les destinations externes sont rejetées.

### 7. Interface et fiche publique — présent

- Vues dédiées : `public.professionals.index`, `public.professionals.show`, et composants partagés pour les cartes et filtres.
- Styles et JavaScript de page séparés dans les entrées Vite dédiées.
- Les vues présentent identité professionnelle, métier, zone de service, compétences, expérience déclarée, services publiés, avis et favoris selon le contexte.
- La fiche charge explicitement les champs utiles et ne rend pas l’e-mail ni l’adresse personnelle publique.
- Les services visibles sont filtrés par publication et catégorie active.

### 8. Recherche, tri, pagination et performance — présent

- Les filtres couvrent texte, métier, catégorie, compétences, ville/province, prix, devise/unité, note minimale, disponibilité déclarée et vérification.
- Les tris sont limités par une liste autorisée; les prix comparables exigent une devise et une unité.
- La pagination conserve les paramètres de recherche et `per_page` est borné.
- Les relations de cartes sont chargées à l’avance et le test API vérifie un plafond de requêtes pour prévenir une régression N+1.
- La disponibilité est explicitement affichée comme déclarée, et non comme une garantie temps réel.

### 9. Sécurité et tests — couverture présente

Les tests repérés couvrent notamment :
- l’exclusion des profils privés, brouillons, suspendus et des comptes suspendus;
- l’accès direct aux fiches publiques et les URLs canoniques;
- la protection des données privées dans les ressources API;
- le badge de vérification sans date d’approbation;
- les filtres, tris, pagination et limites de requêtes;
- les favoris, la propriété, les profils suspendus et le retour après connexion;
- les avis et leur recalcul après modération.

## Décisions de maintenance

1. Conserver `ProfessionalProfile`, `ProfessionalSearchService`, `ProfessionalSearchRequest` et les composants Blade existants; éviter toute architecture concurrente.
2. Ne pas créer de tables d’abonnement/promotion tant qu’un besoin métier confirmé et un modèle cohérent ne sont pas spécifiés.
3. Toute évolution de note doit inclure tests de création, masquage et republication d’avis, puis vérifier la cohérence entre carte, filtre, tri, ressource API et fiche.
4. Toute modification de publication doit être couverte par un test d’accès direct et par un test de recherche.
5. Ne déclarer la release certifiée qu’après une CI verte sur le commit final exact.
