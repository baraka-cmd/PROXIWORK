# Phase 3 — Final Review

## 5.13 Messaging

- Conversation is the aggregate; participants are explicit in `conversation_participants`.
- Messages are append-only records linked to a conversation.
- V1 client/professional conversations use a unique participant pair.
- `ConversationType::SUPPORT` is reserved for a future support workflow without fabricating one now.
- Every conversation operation requires active participation.
- Service-request/order context is validated against both participants.
- Messages are capped at 5,000 characters, duplicate sends are rejected, and `message-send` rate limiting is active.
- Cursor pagination prevents unbounded message retrieval.
- `last_read_message_id` is stored per participant for unread counts.
- Conversation creation, message append and read markers use row locks plus database constraints.

## 5.14 Transactional Notifications

Covered events: new service request, new quotation, quotation accepted/rejected/counter-offer, payment confirmed, order confirmed, new message, new review.

`TransactionalNotificationService` centralizes delivery while `AccountActivityNotification` remains preference-aware.
Notifications are emitted after successful domain operations.

## 💰 FINANCE
- Payment amount/currency remain server-authoritative.
- Payment idempotency, transaction uniqueness, commission and wallet ledger protections remain active.
- Commission is derived from successful payment data.
- No floating-point monetary calculation was introduced.
- Refund/reversal accounting remains intentionally deferred; historical facts are not rewritten.

## 🔐 SECURITY
- Sanctum, role/permission middleware, policies, throttling and security headers remain active.
- Messaging is participant-authorized and protected against IDOR.
- Review ownership and professional identity are server-derived.
- Payment and notification recipients are derived from trusted server-side relationships.
- Pagination and message-size limits are enforced server-side.

## 🔄 CONCURRENCY
Critical locking/uniqueness remains active for payments, webhooks, orders, commission, wallet operations, withdrawals, quotations, service requests, reviews and messaging.

Pattern: `BEGIN → lock resource → verify invariant → write → COMMIT`.
Database uniqueness remains the final integrity barrier.

## 🧾 AUDIT
Order status history, service-request histories, quotation events, payment transactions and wallet ledger remain the authoritative historical records.
The existing audit-log foundation remains available for administrative/security actions. No parallel fake audit system was introduced.

## 🧪 TESTS
Coverage includes payment idempotency/webhooks, amount manipulation, wallet concurrency, commission idempotency, review ownership/duplication/moderation, messaging authorization/unread/pagination/duplicate protection/size limits, and transactional notification coverage.

CI exit condition: complete Laravel suite green and Pint green.

## Phase 3 exit criteria
- [x] 5.1 Service Requests
- [x] 5.2 Request Lifecycle
- [x] 5.3 Quotations
- [x] 5.4 Negotiation
- [x] 5.5 Orders
- [x] 5.6 Address Snapshot
- [x] 5.7 Payment Abstraction
- [x] 5.8 Payment Transactions
- [x] 5.9 Idempotency / Concurrency
- [x] 5.10 Professional Wallet
- [x] 5.11 Commission
- [x] 5.12 Reviews
- [x] 5.13 Messaging
- [x] 5.14 Transactional Notifications
- [x] 5.15 Final Phase 3 Review