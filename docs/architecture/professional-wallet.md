# Portefeuille professionnel — point 34

## Périmètre

La page `/professional/wallet` est un écran de consultation des soldes et des mouvements du portefeuille du professionnel authentifié. Elle ne doit pas effectuer de mutation financière lors d'une requête GET.

Les trois compartiments exposés par le modèle existant sont :

- `available_balance` : solde actuellement disponible pour une demande de retrait, sous réserve des contrôles du service financier ;
- `pending_balance` : montants en attente de libération ;
- `locked_balance` : montants réservés pour des opérations en cours.

Chaque solde est affiché uniquement dans la devise du portefeuille choisi. Les soldes de devises différentes ne sont jamais additionnés.

## Source de vérité et architecture

Le modèle `Wallet` et les écritures de `WalletTransaction` restent les sources de données existantes. Les services financiers continuent à être les seuls responsables des changements de solde. Le contrôleur Web ne modifie ni le portefeuille, ni les transactions, ni les retraits.

Flux de lecture :

`Route protégée → ProfessionalWalletFilterRequest → WalletController → requêtes bornées par le ProfessionalProfile authentifié → Blade`

Les lectures Web et API n'appellent plus `WalletService::getOrCreate()` pour une simple consultation. L'interface Web affiche un état vide lorsqu'aucun portefeuille n'existe ; l'API retourne 404 tant qu'aucun portefeuille n'a été créé par une opération financière autorisée.

## Filtres de l'historique

Les filtres serveur sont limités au portefeuille de la devise sélectionnée :

- type d'opération ;
- sens (crédit ou débit) ;
- identifiant exact du mouvement ;
- période personnalisée, obligatoirement cohérente et limitée à 366 jours.

L'historique est paginé à 15 écritures par page. Les filtres sont validés côté serveur et conservés lors de la pagination. Une devise qui ne correspond à aucun portefeuille du professionnel ne permet pas de lire les données d'un autre compte.

## Présentation et exactitude

- Le tableau n'affiche pas `balance_after` comme un solde global : dans le schéma actuel, les services enregistrent parfois le solde disponible et parfois le solde en attente dans les champs `balance_before` / `balance_after`, sans champ indiquant le compartiment concerné. Présenter ce champ comme un solde universel serait trompeur.
- Les montants visibles sont formatés à partir de leur représentation décimale, sans conversion en nombre flottant.
- L'écran expose séparément disponible, en attente et bloqué/réservé.
- L'état vide, les erreurs de filtre, l'état de portefeuille non actif et l'avertissement de limites du registre sont explicitement présentés.
- L'action de demande de retrait n'est pas affichée lorsque le portefeuille n'est pas actif ; le service de retrait doit également faire respecter cette règle côté serveur dans le point dédié.
- L'historique affiché est celui des écritures actuellement enregistrées ; il n'est pas présenté comme un relevé comptable certifié.

## Sécurité

- Le middleware de rôle professionnel protège la route ; le FormRequest vérifie également le rôle.
- Le profil est résolu à partir de l'utilisateur connecté ; les requêtes de portefeuille et de mouvements sont toujours filtrées par ce profil.
- L'autorisation de consultation du portefeuille reste appliquée.
- Aucun solde, type d'opération, référence financière ou propriétaire n'est accepté depuis le client comme source d'autorité.
- Les filtres ne déclenchent aucune mutation.
- La requête GET n'instancie pas de portefeuille absent.

## Limites à résoudre dans le flux financier

Le schéma actuel ne précise pas le compartiment auquel se rapporte chaque `balance_before` / `balance_after`. De plus, les transitions finales de certains retraits ne créent pas toutes une écriture dédiée dans `wallet_transactions`. Ces limites sont maintenant signalées dans l'interface plutôt que masquées par un affichage ambigu.

Avant de qualifier l'historique de grand livre complet, le point Retraits doit définir et tester les écritures compensatoires/terminales, leur idempotence, les références de rapprochement et le comportement en cas d'échec ou de succès. Les remboursements restent également soumis au suivi de rapprochement financier documenté dans l'issue #136.

## Tests attendus

La suite dédiée vérifie au minimum :

- une consultation Web ou API sans portefeuille ne crée ni portefeuille ni mouvement ;
- l'API ne retourne que le portefeuille existant du professionnel authentifié ;
- les filtres sont appliqués au bon portefeuille et à la période demandée ;
- les écritures d'un autre professionnel ne sont jamais visibles ;
- une devise inexistante pour le professionnel est rejetée ;
- les périodes supérieures à 366 jours sont rejetées ;
- le formateur conserve les décimales sans conversion flottante.
