# Phase 5.3 — Espace financier professionnel (P-R21 à P-R28)

## Objectif

Moderniser les interfaces existantes de revenus, portefeuille et retraits sans modifier les règles financières ni reconstruire les parcours Laravel.

## Audit du fonctionnement existant

- **Revenus** : `RevenueController` délègue les indicateurs et historiques à `RevenueAnalyticsService`. Les commissions comptabilisées, paiements confirmés, commandes terminées, remboursements et soldes en attente sont des agrégats distincts. Les devises sont groupées séparément. Les filtres `nature`, `status` et `reference` affectent l'historique et l'export, mais pas tous les indicateurs synthétiques.
- **Portefeuille** : `WalletController` sélectionne un portefeuille par devise, autorise sa consultation et applique les filtres au registre de mouvements avec pagination. Les trois compartiments du solde sont fournis par le serveur. Le registre existant avertit qu'il ne constitue pas encore un relevé comptable certifié.
- **Retraits** : `WithdrawalController` et `WithdrawalService` gèrent l'éligibilité du portefeuille, la réservation, la clé d'idempotence, les transitions et l'historique. Les canaux Mobile Money et virement sont des demandes de traitement manuel ; aucun fournisseur réel n'est annoncé comme intégré. Une demande créée n'est pas une preuve de paiement exécuté.
- **Statuts** : les libellés affichés proviennent des statuts existants. Aucune transition ou étape de traitement n'est simulée dans le navigateur.

## Changements UI/UX

- Navigation cohérente entre revenus, portefeuille et retraits.
- Mise en évidence de la devise et amélioration de la hiérarchie des cartes financières, des filtres, tableaux, références et états vides.
- Libellés français cohérents dans le détail d'une commission.
- Export CSV libellé avec sa portée : critères actuels, hors pagination, et rappel des filtres qui n'affectent pas les indicateurs.
- Formulaire de retrait organisé en étapes visuelles, avec erreurs reliées aux champs et rappel explicite de la réservation.
- Couleurs des statuts harmonisées : succès, attente, vérification, échec/refus et annulation neutre ; le texte du statut reste visible.
- Mise au point responsive, focus clavier, chiffres alignés et respect de la préférence de réduction des animations.
- États vides des retraits distinguant l'absence d'historique d'une recherche sans résultat.
- Les états vides de graphique sont rendus avec l'API DOM et `textContent`, sans injecter de HTML.

## Limites volontairement préservées

Aucun contrôleur, service métier, FormRequest, policy, modèle, enum, route, migration, règle de calcul, solde, frais, statut ou mécanisme d'idempotence n'est modifié. Le navigateur n'est pas autorisé à recalculer les soldes ou à décider de la réussite d'un retrait. Aucun frais, montant net estimé, fournisseur ou délai n'est inventé.

## Inspirations consultées

- Stripe — présentation du solde et des réserves : https://support.stripe.com/questions/view-reserve-status-and-details-in-dashboard
- Wise — filtres de transaction et export de la vue courante : https://wise.com/help/articles/2489458/how-do-i-download-my-transaction-history
- Payoneer — suivi des retraits depuis l'historique des transactions : https://payoneer.custhelp.com/app/answers/detail/a_id/18605/~/withdraw-to-bank---faq
- Principes de conception fintech et accessibilité : hiérarchie de l'information, portée des données, statuts textuels, alternative aux graphiques et focus clavier.

## Vérifications

- Les tests automatiques, la compilation Vite, les contrôles de style et les migrations doivent être confirmés par la CI de la PR.
- La revue manuelle du rendu sur desktop, tablette et mobile, ainsi que les parcours clavier et lecteur d'écran sur une session professionnelle authentifiée, restent à réaliser dans une instance exécutée.
