# PROXIWORK — Avis professionnels et réputation

## Principes métier

- Un client ne peut évaluer qu'une commande qui lui appartient et dont le statut est `completed`.
- Une commande ne possède qu'un avis, garanti par la contrainte unique sur `reviews.order_id`.
- Une note est un entier compris entre 1 et 5; le commentaire peut être absent.
- Le professionnel évalué peut consulter les avis liés à son profil et répondre uniquement aux avis publiés.
- Une réponse est unique par avis. La modification n'est pas activée dans cette version; toute évolution devra définir l'historique et les règles de modération avant d'autoriser les éditions.
- Le professionnel ne peut pas modifier la note ni supprimer un avis. Il peut demander un examen à l'équipe de modération par un signalement.
- Un signalement n'entraîne jamais un masquage automatique. La décision appartient à un modérateur habilité.
- Les agrégats de réputation sont calculés côté serveur à partir des avis publiés. Les avis masqués ne doivent pas contribuer à la moyenne ni au compteur public.

## Modèle de données existant

Aucune nouvelle table n'est ajoutée pour ce module. Les tables `reviews`, `review_responses`, `reports`, et `moderation_actions` existent déjà.

- `reviews.order_id` est unique et possède une clé étrangère vers `orders`.
- `reviews.client_id` et `reviews.professional_id` conservent l'auteur et le professionnel évalué.
- `reviews.status`, `published_at`, `moderated_at`, `moderated_by`, `moderation_reason` décrivent l'état de publication et la modération.
- `review_responses.review_id` est unique. La réponse possède son propre statut et ses champs de modération.
- Les signalements réutilisent le workflow de modération existant; aucune seconde file de signalements n'est créée.

## Réputation et indicateurs

La page professionnelle présente :
- la moyenne des avis publiés, ou « aucun avis » lorsqu'il n'y en a pas;
- le total publié et la distribution des notes de 1 à 5;
- le nombre d'avis sans réponse publiée et avec réponse publiée;
- le volume et la moyenne de la période choisie (7, 30 ou 90 jours);
- une comparaison avec la période précédente de même durée uniquement lorsque les deux périodes possèdent des avis;
- des thèmes indicatifs détectés par mots-clés réels dans les commentaires, limités aux 500 commentaires publiés les plus récents et affichés uniquement avec un minimum de cinq avis publiés et cinq commentaires analysables.

L'analyse des thèmes est heuristique et non une analyse de sentiment ni une décision de modération. Un thème peut être absent du texte même si le client a un problème; les commentaires restent la source primaire. Les catégories peuvent se chevaucher, car un commentaire peut mentionner plusieurs sujets.

## Liste, filtres et confidentialité

La liste est paginée côté serveur et conserve les paramètres dans les liens de pagination. Les filtres sont combinables : note, statut, période de publication, service, réponse publiée, présence d'un commentaire, texte de recherche et tri. La recherche utilise des paramètres SQL liés; les entrées ne sont pas concaténées dans la requête SQL.

La page professionnelle ne révèle pas le nom complet du client. Elle n'affiche pas d'adresse, de téléphone ni de détails privés de commande. La référence de commande et le service sont affichés uniquement dans le contexte authentifié du professionnel propriétaire. Le profil public n'expose que les avis publiés et les réponses publiées.

## Réponses, notifications et audit

- La réponse est validée côté serveur et protégée par une politique d'autorisation.
- Les soumissions sont limitées en débit et le formulaire désactive le bouton après soumission pour réduire les doubles clics. La contrainte unique sur `review_responses.review_id` demeure la garantie d'intégrité.
- La publication d'un avis notifie le professionnel; une réponse publiée notifie le client; les décisions de modération notifiables informent le professionnel.
- Les événements de création, réponse et modération sont journalisés dans le système d'audit existant.
- Un même signalement ouvert par le même déclarant pour la même cible est refusé afin de réduire les doublons accidentels.

## Sécurité et limites

Les routes professionnelles exigent une session active et le rôle professionnel. Les réponses vérifient l'appartenance de l'avis au professionnel. Le signalement web vérifie explicitement que le professionnel connecté est bien celui évalué et applique une limitation de débit. La modération administrative reste soumise aux permissions existantes.

Le rattachement à une commande terminée est une preuve d'admissibilité de l'avis, pas une garantie que chaque affirmation du commentaire est vraie. Les avis défavorables ne doivent pas être supprimés uniquement à cause de leur note.

## Vérifications à exécuter avant fusion

- Tests de création d'avis, commande non admissible, mauvais client et doublon.
- Tests de listing, pagination, filtres combinés et isolation entre professionnels.
- Tests de réponse, double réponse, réponse à un avis masqué et notification.
- Tests de signalement propriétaire/non-propriétaire, doublon de signalement et permission de modération.
- Tests de cohérence des agrégats après publication et masquage d'un avis.
- CI complète : suite Laravel, migrations MySQL fraîches, build frontend et style PHP.
