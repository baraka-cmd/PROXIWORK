# PROXIWORK — Audit de consolidation de `audit/current-project-baseline`

**Date de vérification : 14 octobre 2026**  
**Branche cible :** `audit/current-project-baseline`  
**Dernière intégration fonctionnelle :** PR #142, commit de merge `91dc69fc20e65855d9211b1db3a1876b4f5a4e6c`

## Conclusion

Les modules Web des 37 fonctionnalités prévues sont présents dans la branche de référence : authentification et rôles, découverte publique, parcours client, parcours professionnel, revenus, portefeuille, retraits, messagerie et notifications. Les routes principales ont été vérifiées dans `routes/web.php`. Les tests de réinitialisation de mot de passe sont présents dans `tests/Feature/PasswordResetTest.php` (5 tests) et `tests/Feature/AuthenticationTest.php` (couverture complémentaire).

Cette conclusion signifie que le code fonctionnel demandé est consolidé et testable. Elle **ne signifie pas** que les paiements ou les retraits réels sont prêts à être activés en production : les limites ci-dessous restent importantes et explicites.

## Intégrations vérifiées

| Domaine | PR(s) intégrée(s) | État |
|---|---|---|
| Favoris, recherche publique, demandes et devis client | #116–#119 | Intégré |
| Commandes et paiements client | #120–#121 | Intégré |
| Profil, adresses et notifications client | #122–#124 | Intégré |
| Messagerie client | #126 | Intégré ; la PR #125 est une variante non fusionnée |
| Dashboard professionnel | #127 | Intégré |
| Profil professionnel et langues/modes d’exécution | #128–#129 | Intégré |
| Catalogue de services professionnel | #130 | Intégré |
| Demandes, devis et commandes professionnels | #131 | Intégré |
| Avis et réputation professionnelle | #132–#134 | Intégré |
| Revenus professionnels | #135 | Intégré par merge commit `6f7ad0a70c8c04e4109e618ca42eff648e1e08cd` |
| Portefeuille professionnel | #138 | Intégré par merge commit `82240b0fe59f1c210e236a6eafd5ca0c64102f77` |
| Retraits professionnels et traitement administratif | #139 | Intégré par merge commit `0f88c6699e45bda9fb5ea7c078e2316eff5bf5c8` |
| Messagerie/notifications professionnelles durcies | #142 | Intégré par merge commit `91dc69fc20e65855d9211b1db3a1876b4f5a4e6c` |

Les fondations historiques couvrent aussi l’inscription, la connexion/déconnexion, les sessions, la réinitialisation du mot de passe, la vérification d’e-mail, la redirection selon le rôle et le RBAC. Les routes et tests d’authentification ont été contrôlés dans la branche de référence.

## Vérifications CI observées

Sur le HEAD de la PR #142, qui contient le même arbre fonctionnel que l’intégration finale :

- Suite Laravel : **631 tests, 2 650 assertions — succès**.
- Validation du style PHP avec Pint sur les fichiers modifiés — succès.
- Build frontend Vite — succès.
- Validation Composer — succès.
- Migrations MySQL sur une base vierge — succès.
- Tests de régression financière MySQL — succès.

Les PR financières empilées ont aussi été testées individuellement :
- PR #135 : 591 tests, 2 438 assertions.
- PR #138 : 602 tests, 2 478 assertions.
- PR #139 : 626 tests, 2 619 assertions, avec tests MySQL financiers et test de concurrence multi-processus.

Ces résultats CI valident le code couvert par les workflows ; ils ne remplacent pas une recette manuelle complète des parcours ni une validation des règles métier par le propriétaire de la plateforme.

## Points à ne pas déclarer certifiés pour la production

1. **Issue #136 — rapprochement financier : ouverte.** Les remboursements confirmés, commissions et mouvements du portefeuille ne sont pas encore reliés par un flux de correction comptable complet. Le reporting des revenus sépare ces événements et ne prétend pas fournir un grand livre certifié.
2. **Issue #140 — règles métier des retraits : ouverte.** Les montants minimum/maximum, plafonds périodiques, barème des frais, exigences d’identité, SLA et destinations autorisées doivent être décidés et approuvés avant l’activation réelle.
3. **Versements réels :** les canaux Mobile Money et virement bancaire du module de retrait représentent un processus manuel. Aucun fournisseur réel de versement n’est certifié par cette intégration.
4. **Données historiques sensibles :** les destinations de retrait qui auraient été stockées en clair par une ancienne version nécessitent une migration contrôlée distincte ; elles ne sont pas rechiffrées automatiquement par la PR #139.
5. **Canaux externes de notification :** SMS et push dépendent de fournisseurs et d’une configuration effectifs ; la présence des préférences dans l’interface ne garantit pas l’envoi.

## Consigne pour la récupération locale

La branche `audit/current-project-baseline` est la référence consolidée pour récupérer le code et poursuivre la recette. Avant une mise en production, traiter les issues #136 et #140, configurer les vrais fournisseurs nécessaires, puis exécuter une recette fonctionnelle sur l’environnement cible. Ne pas présenter la plateforme comme comptablement certifiée ni comme un service de versement réel avant ces étapes.
