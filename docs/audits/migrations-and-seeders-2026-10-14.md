# Audit des migrations et seeders — PROXIWORK

**Date :** 14 octobre 2026  
**Branche auditée :** `audit/current-project-baseline`  
**Commit audité avant ce rapport :** `91dc69fc20e65855d9211b1db3a1876b4f5a4e6c`

## Résultat exécuté en CI

Le workflow `.github/workflows/ci.yml` contient un job MySQL 8.4 qui effectue, dans cet ordre :

1. `php artisan migrate --force` sur une base MySQL vierge ;
2. `php artisan db:seed --force` ;
3. un deuxième `php artisan db:seed --force` sur la même base ;
4. les tests de régression financiers MySQL et le test de réservation concurrente des retraits.

Sur le commit testé de la PR #142, le job **Fresh MySQL migration** est terminé avec succès. Les 82 migrations sont passées, puis `RbacSeeder`, `CategorySeeder` et `DemoPlatformSeeder` ont chacun terminé avec succès deux fois. Cela confirme sur MySQL 8.4 l'ordre de création des tables et l'idempotence observée des seeders de démonstration.

## Inventaire

- **82 fichiers de migration** sous `database/migrations/`.
- **4 seeders** : `DatabaseSeeder.php`, `RbacSeeder.php`, `CategorySeeder.php`, `DemoPlatformSeeder.php`.
- `DatabaseSeeder` appelle les seeders dans l'ordre : RBAC, catégories, données de démonstration.
- `DemoPlatformSeeder` suit l'ordre métier : utilisateurs/accès, adresses/préférences, compétences/profils professionnels, services/favoris, demandes/devis, commandes/paiements, portefeuille/avis, messagerie/notifications/support, puis modération/audit.

## Ordre et dépendances

Les migrations sont triées par leur nom complet de migration (le préfixe date/heure, puis le suffixe). Les dépendances importantes sont ordonnées et la migration MySQL vierge confirme notamment que :

- `users` existe avant les tables qui référencent les utilisateurs ;
- les profils professionnels et services existent avant les demandes ;
- les demandes, devis et offres existent avant les commandes ;
- les commandes et intentions de paiement existent avant les paiements ;
- les portefeuilles existent avant les transactions de portefeuille et les retraits ;
- les tables de conversations/messages existent avant leurs extensions et pièces jointes ;
- les tables de notifications existent avant les migrations d’archivage et de déduplication.

## Avertissement : préfixes date/heure dupliqués

L'inventaire trouve **14 groupes de préfixes date/heure partagés par 31 fichiers**. Les noms complets des fichiers restent distincts ; Laravel les trie ensuite selon leur nom complet, et cet ordre a réussi lors de l'exécution sur MySQL 8.4.

| Préfixe partagé | Fichiers |
|---|---|
| `2026_10_06_000001` | `create_audit_logs_table`, `create_personal_access_tokens_table`, `create_roles_table` |
| `2026_10_06_000002` | `create_notification_preferences_table`, `create_profiles_table` |
| `2026_10_06_000003` | `create_addresses_table`, `create_notifications_table` |
| `2026_10_06_230007` | `create_order_items_table`, `create_payment_intents_table` |
| `2026_10_07_000001` | `create_conversations_table`, `create_payments_table`, `create_professional_verification_reviews_table` |
| `2026_10_07_000002` | `create_conversation_participants_table`, `create_payment_transactions_table` |
| `2026_10_07_000003` | `create_messages_table`, `create_wallets_table` |
| `2026_10_07_000004` | `add_last_message_to_conversations_table`, `create_wallet_transactions_table` |
| `2026_10_12_000002` | `add_pending_email_change_fields_to_users_table`, `create_homepage_slides_table` |
| `2026_10_12_000005` | `add_access_instructions_and_service_request_address_snapshots`, `create_payment_receipts_table` |
| `2026_10_12_000006` | `add_archived_at_to_notifications_table`, `add_service_execution_modes`, `create_payment_refunds_table` |
| `2026_10_12_000007` | `create_notification_deliveries_table`, `create_payment_events_table` |
| `2026_10_13_000001` | `add_service_scope_and_conditions`, `extend_conversations_and_messages_for_reliable_context` |
| `2026_10_13_000002` | `create_message_attachments_table`, `create_service_realizations_table` |

### Décision de sécurité

**Ne pas renommer maintenant les migrations déjà présentes dans cette branche.** Les installations existantes enregistrent les noms de migration dans la table `migrations`; renommer un fichier déjà exécuté peut faire croire à Laravel qu'il s'agit d'une nouvelle migration et provoquer une nouvelle exécution ou un échec.

À partir de maintenant, chaque nouvelle migration doit recevoir un préfixe date/heure unique et être nommée en fonction de l'opération réelle. Si une réorganisation historique devient nécessaire, elle doit être traitée comme une migration corrective conçue explicitement, et non par un simple renommage.

### Migration historique des favoris

`2026_10_06_000000_create_favorites_table.php` est un **placeholder de compatibilité** dont `up()` est volontairement vide. La table est créée par `2026_10_06_000011_create_favorites_table.php`, après la création de `professional_profiles`. Ne pas supprimer le placeholder ni déplacer la création de table sans analyser les bases existantes.

## Verdict

**Ordre des migrations : vérifié sur MySQL 8.4 vierge et réussi. Seeders : exécutés deux fois sur la même base MySQL sans échec.** Les préfixes date/heure dupliqués sont une dette de nommage à éviter pour les prochaines migrations, mais ils ne justifient pas un renommage risqué des migrations historiques avant la récupération locale.
