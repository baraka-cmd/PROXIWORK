# Phase 3 — Final Review

## 5.13 Messaging

### Architecture
- Conversation is the aggregate.
- Participants are explicit in `conversation_participants`.
- Messages are append-only records linked to a conversation.
- V1 client/professional conversations use a unique participant pair.
- `ConversationType::SUPPORT` exists for the future support domain without exposing a fake support workflow now.

### Security
- Every conversation read/send/read-marker operation requires active participation.
- The client cannot choose another participant identity.
- Contextual service-request/order IDs are validated against both participants.
- Message body is server-validated and capped at 5,000 characters.
- Duplicate identical messages inside a short window are rejected.
- Message creation is protected by the `message-send` rate limiter.
- Cursor pagination prevents unbounded message retrieval.
- Sensitive internal fields are not exposed in resources.

### Unread
`last_read_message_id` is stored per participant. Unread counts are calculated only for the authenticated participant and only against messages after that marker.

### Concurrency
Conversation creation locks the professional row and has a database uniqueness constraint. Message creation locks the conversation row before updating `last_message_id`. Read markers are updated under a participant-row lock.

## 5.14 Transactional Notifications

The notification foundation is reused instead of creating a second notification system.

Covered business events:
- new service request;
- new quotation;
- quotation accepted;
- payment confirmed;
- order confirmed;
- new message;
- new review.

Delivery remains preference-aware through `AccountActivityNotification`.

The generic delivery mechanism is centralized in `TransactionalNotificationService`; domain controllers only select the business event.

Notifications are side effects and are emitted after successful domain operations, not before business state is persisted.

## 💰 FINANCE
- Payment amount and currency remain server-authoritative.
- Payment idempotency and transaction uniqueness remain active.
- Commission is derived from the successful payment, never from client input.
- Wallet ledger is immutable and idempotent.
- Pending professional earnings are posted through the existing wallet boundary.
- No floating-point monetary calculation was introduced.
- Refund/reversal accounting remains intentionally deferred to the future refund domain; historical financial facts are not rewritten.

## 🔐 SECURITY
- Sanctum protects authenticated endpoints.
- Role/permission middleware remains active.
- Conversation access is participant-based.
- Review ownership and professional ownership are server-derived.
- Payment amount is never trusted from the client.
- Notification recipients are derived from trusted domain relationships.
- Message rate limiting is enabled.
- Pagination limits are enforced server-side.
- Mass-assignment protections remain in force.
- Existing security headers and API throttling remain active.

## 🔄 CONCURRENCY
Critical financial and reputation operations retain row locking and database constraints:
- payments;
- payment transactions;
- webhook processing;
- order confirmation;
- commission posting;
- wallet balance/ledger operations;
- withdrawals;
- review creation/rating aggregate;
- quotation negotiation;
- service-request lifecycle;
- conversation creation/message append/read marker.

The design follows:
`BEGIN → lock resource → verify invariant → write → COMMIT`
Database uniqueness remains the final integrity barrier.

## 🧾 AUDIT
Existing order status history, service-request history, quotation events, wallet ledger and payment transaction records remain the authoritative historical records for their respective domains.

Phase 3 does not add a parallel fake audit system. The existing audit-log foundation remains available for administrative/security actions.

Important financial records are immutable facts or corrected through compensating operations rather than destructive edits.

## 🧪 TESTS
The Phase 3 test suite covers:
- payment idempotency;
- duplicate webhook handling;
- payment amount manipulation;
- concurrent wallet withdrawal;
- commission idempotency;
- review ownership;
- duplicate reviews;
- review moderation and rating aggregation;
- messaging authorization;
- unread counts;
- message pagination;
- duplicate-message protection;
- message size limits;
- transactional notification coverage.

CI must finish with the complete Laravel test suite and Pint green before merge.

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