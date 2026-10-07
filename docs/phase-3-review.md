# Phase 3 — Final Review

## Scope

Phase 3 covers the transaction layer:

- Service Requests
- Request Lifecycle
- Quotations
- Negotiation
- Orders
- Order Address Snapshot
- Payment Abstraction
- Payment Transactions
- Idempotency / Concurrency
- Professional Wallet
- Commission
- Reviews
- Messaging
- Transactional Notifications

## Review gates

### Finance
- Server-side order amount is authoritative.
- Payment transactions are idempotent.
- Commission is created once per payment.
- Only net professional earnings are posted to pending wallet balance.
- Wallet mutations are protected by row locks and idempotency keys.
- Withdrawal locks are covered by regression tests.

### Security
- Payment endpoints require authentication and client ownership.
- Order access is isolated by policy.
- Webhook callbacks require HMAC signatures.
- Webhook amount/currency are checked against the stored transaction.
- Notifications are scoped to the current user.
- Conversations require participant authorization.
- Message sending is protected by conversation authorization.

### Concurrency / idempotency
- Payment intent uniqueness is enforced at database level.
- Payment transaction idempotency is enforced at database level.
- Provider transaction/event identifiers are unique.
- Wallet transactions use idempotency keys.
- Financial mutations use database transactions and row locks.
- Provider integrations are required to honor the same idempotency key.

### Auditability
- Order status history records payment confirmation.
- Payment, transaction, commission and wallet records retain durable references.
- Phase 1 audit logging remains part of the regression suite.

### Quality / architecture
- Controllers, Requests, Resources and Services are separated by feature responsibility.
- Policies remain in Laravel's conventional `app/Policies` location so auto-discovery is preserved.
- Financial services are grouped under `app/Services/Payment`, `Wallet`, and `Commission`.
- Messaging and notifications have dedicated service/controller/resource namespaces.
- No artificial Repository/DTO layer was introduced where Laravel conventions are sufficient.

## Findings fixed during this review

1. Phase 3 branches were not included in the CI workflow triggers.
2. `NotificationController` was missing the `NotificationPreference` model import.
3. HTTP-level webhook tests were missing; valid and invalid signature paths are now covered.

## CI evidence

Final validation run on the review branch:

- GitHub Actions CI: **PASS**
- PHPUnit/Laravel test suite: **PASS**
- Assertions: **729**
- Pint: **PASS**
- Test warnings: **211 existing PHPUnit warnings**; they do not fail the suite.

## Acceptance

Phase 3 is ready to be merged into `phase-3/finalize` after this review branch is accepted.

The next roadmap gate is **Phase 4 — Administration**.
