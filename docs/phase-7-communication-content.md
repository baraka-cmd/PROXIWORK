# Phase 7 — Communication et contenus

## Périmètre

Cette livraison ajoute les fonctions administratives des points 46 à 52 en réutilisant les services déjà présents.

- Notifications : consultation des livraisons par canal, détail et relance uniquement des échecs explicites.
- Historique : notification_deliveries reste la source canonique des états de livraison et des tentatives.
- Modèles : modèles de notifications versionnés par événement et langue, avec sélection selon la langue du profil et repli français ; variables autorisées (prenom, nom, email, plateforme). Les événements transactionnels utilisent le modèle actif correspondant à leur clé d'action, avec le texte historique comme repli.
- Annonces : publication sur une page publique avec audience et fenêtre de visibilité.
- Communications ciblées : campagnes vers les comptes actifs et vérifiés, audience calculée côté serveur, déduplication par campagne et utilisateur, livraisons confiées aux jobs de notification existants.
- Pages publiques : pages éditoriales en texte brut, SEO de base et historique de versions. Les pages métier conservent leur rendu Laravel.
- FAQ et institutionnel : catégories et questions par audience ; contenus institutionnels versionnés avec date d'effet.
- Page d'accueil : administration des diapositives existantes homepage_slides, avec validation des images et destinations internes.

## Sécurité

- Toutes les routes d'administration utilisent auth, active.account et des permissions dédiées.
- Les campagnes n'acceptent jamais de liste de destinataires fournie par le navigateur. Le serveur résout les rôles et critères autorisés.
- Les campagnes ciblent uniquement les comptes actifs et vérifiés.
- Les jobs email/SMS/push existants restent responsables des préférences, de la configuration fournisseur et du résultat de livraison.
- Les statuts UNKNOWN ne sont jamais relancés automatiquement. L'action de relance n'accepte que les échecs explicites et refuse les erreurs dont le résultat peut être incertain.
- Les contenus publics sont rendus en texte échappé ; aucun HTML ou Blade fourni par l'administrateur n'est exécuté.
- Les destinations du carrousel sont limitées à des chemins internes autorisés.
- Les opérations de création, modification, publication, archivage et relance sont journalisées dans l'audit existant.

## Déploiement

Les migrations sont ajoutées sans modifier les migrations déjà exécutées. Après fusion :

1. Exécuter php artisan migrate --force.
2. Exécuter php artisan db:seed --class=RbacSeeder --force pour enregistrer les permissions.
3. Vérifier qu'un worker Laravel traite les files d'attente.
4. Vérifier que le scheduler Laravel exécute schedule:run ; il déclenche communications:dispatch-scheduled chaque minute.
5. Vérifier la configuration du disque public pour les images de diapositives, puis le lien de stockage si nécessaire.

## Limites à garder visibles

- SENT signifie que le transport a accepté l'envoi, pas que le message a été lu ou livré dans la boîte de réception.
- Les annonces sont consultables sur /announcements ; elles ne déclenchent pas automatiquement un email ou un SMS.
- Les campagnes créent des notifications internes et des livraisons externes uniquement sur les canaux choisis. Les résultats finaux dépendent des fournisseurs et des préférences.
- Les diapositives réutilisent le stockage public existant ; en production, les fichiers doivent être persistants sur Render.
- Les contenus juridiques doivent être validés par un responsable habilité avant publication. Cette fonctionnalité conserve les versions mais ne remplace pas un processus de validation juridique.
