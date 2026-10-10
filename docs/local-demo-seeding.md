# Données de démonstration PROXIWORK

Les seeders remplissent une base locale avec des comptes, profils, catégories, compétences,
professionnels, services, demandes, devis, commandes, paiements simulés, portefeuilles,
avis, conversations, notifications, tickets de support et données de modération.

## Préparer et remplir une base locale

Dans le dossier du projet :

\`\`\`bash
php artisan migrate --seed
\`\`\`

Si les migrations sont déjà appliquées :

\`\`\`bash
php artisan db:seed
\`\`\`

Le seeder est conçu pour être relancé sans créer une nouvelle copie de ses données principales.
Le pipeline CI exécute les migrations MySQL puis lance les seeders deux fois afin de contrôler
leur compatibilité et leur répétabilité.

## Comptes locaux de démonstration

Mot de passe commun : \`ProxiworkDemo!2026\`

| Rôle | Adresse de connexion |
| --- | --- |
| Administrateur | \`admin@proxiwork.test\` |
| Modérateur | \`moderation@proxiwork.test\` |
| Support | \`support@proxiwork.test\` |
| Client | \`mireille.client@proxiwork.test\` |
| Client | \`david.client@proxiwork.test\` |
| Professionnel | \`samuel.dev@proxiwork.test\` |
| Professionnelle | \`esther.electricite@proxiwork.test\` |
| Professionnel | \`jp.plomberie@proxiwork.test\` |

Ces identifiants sont réservés au développement local. Changez-les avant toute utilisation
sur un environnement partagé.

## Important

- Toutes les personnes, adresses, demandes, avis et références de paiement sont fictifs.
- Les paiements utilisent le fournisseur \`fake\` et ne contactent aucun opérateur Mobile Money.
- Les chemins d’images sont des chemins de démonstration ; ajoutez des fichiers locaux si vous
  souhaitez afficher des images réelles dans l’interface.
- Ne lancez pas \`php artisan migrate:fresh --seed\` sur une base qui contient des données à garder :
  cette commande supprime toutes les tables avant de les recréer.
- N’utilisez pas les comptes de démonstration en production.
