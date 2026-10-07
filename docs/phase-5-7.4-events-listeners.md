# Phase 5.4 — Events / Listeners

## Principle

An event represents a meaningful domain fact.

A listener performs secondary work triggered by that fact.

We do not introduce events merely to replace direct method calls.

## First domain event

OrderPaid represents the fact that an order payment has been successfully confirmed.

It is emitted from both payment success paths:

- direct payment provider result;
- payment webhook.

The event is dispatched only after the surrounding transaction commits.

## Listener

SendOrderPaidNotification is queued.

It notifies:

- the client;
- the professional user.

The financial mutation itself remains synchronous and transactional.

## Why this boundary matters

The payment transaction remains the source of truth.

The event/listener layer handles secondary work:

- notifications;
- future integrations;
- analytics;
- non-critical side effects.

A notification failure must not roll back a successful payment.

## Transaction safety

OrderPaid implements ShouldDispatchAfterCommit.

This prevents a queued listener from observing an order/payment state that has not committed yet.

## Future events

Potential future domain events include:

- OrderCompleted;
- OrderCancelled;
- VerificationApproved;
- VerificationRejected;
- WithdrawalApproved;
- SupportTicketResolved.

Each should be introduced only when there is a real secondary workflow to decouple.

## Anti-patterns avoided

- event emitted before durable financial commit;
- listener performing core financial mutation;
- dozens of events for trivial CRUD changes;
- synchronous heavy work hidden behind an event;
- event listeners used as an uncontrolled dependency graph.

## Result

7.4 introduces one meaningful domain event with a queued secondary listener and a feature test proving the payment success path emits the event.
