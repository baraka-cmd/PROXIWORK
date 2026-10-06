# Phase 3 — Commission & Reviews

## 5.11 Commission

The financial flow is:

Payment succeeded → gross → commission → net → pending professional wallet.

### Rules

- Gross is taken from the server-side Payment.amount.
- The client cannot choose the gross amount or currency.
- V1 commission type is percentage.
- The rate is configurable with PROXIWORK_COMMISSION_RATE; the development example defaults to 10.00%.
- Monetary arithmetic is performed in integer minor units, not floating-point arithmetic.
- Commission is rounded deterministically to the nearest cent.
- Invariant: gross = commission + net.
- A successful payment produces at most one commission record.
- The commission row is immutable as a financial fact; future refund/reversal flows must create compensating financial operations rather than rewriting history.
- Only the professional net is posted to pending_balance.
- Wallet posting uses the existing idempotent ledger.
- order_id and payment_id are unique in commissions, providing database-level duplication protection.
- Payment/webhook processing remains idempotent and concurrency-safe.

Example:

gross       = 500.00 USD
rate        = 10.00%
commission  = 50.00 USD
net         = 450.00 USD

500.00 = 50.00 + 450.00

The platform commission is currently recorded as a financial fact. A separate platform revenue wallet/accounting module is intentionally not fabricated here.

### Refunds and reversals

Refund handling is not implemented in 5.11 because the current order/payment lifecycle has no finalized refund domain. The commission model contains reversal metadata so a later refund module can introduce compensating entries without editing historical amounts.

## 5.12 Reviews

### Eligibility

A review is allowed only when:

1. the caller is authenticated as a client;
2. the client owns the order;
3. the order is COMPLETED;
4. the order has a target professional;
5. no review already exists for the order.

The server derives client_id and professional_id from the order. They are never accepted as trusted client input.

### Anti-abuse

- One review per order through both service validation and a unique database constraint.
- Rating is strictly 1–5.
- Comment is optional and capped at 2,000 characters.
- IDOR is protected by ownership and policy checks.
- A professional cannot respond to an unrelated review.
- One professional response per review through a unique database constraint.
- Review and response content has an explicit moderation state.
- Moderators with reviews.moderate can publish/hide reviews and responses.
- Hiding a review immediately removes it from the professional rating aggregate.
- Republishing restores it to the aggregate.

### Rating aggregate

The professional profile keeps rating_average and rating_count.

These are recalculated from published reviews while the professional profile row is locked, preventing concurrent review submissions from producing inconsistent aggregates.

### Moderation

V1 performs strong server-side eligibility validation and publishes valid reviews immediately. Administrative moderation can subsequently move content between published and hidden.

Full moderation dashboard, reporting workflows and advanced abuse detection remain Phase 4 concerns; no artificial machine-learning moderation layer is introduced in Phase 3.

## Security invariants

- No client-controlled professional identity.
- No client-controlled financial amount.
- No arbitrary review status update.
- No arbitrary commission amount.
- Database uniqueness protects financial and reputation duplication.
- Domain services enforce business ownership and lifecycle rules.
- Existing Sanctum, role, permission, throttling and security-header middleware remain the outer security boundary.