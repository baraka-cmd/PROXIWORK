# Phase 5.3 — Queues

## Current state

The application already has a queued notification foundation:

- AccountActivityNotification implements ShouldQueue;
- database and Redis queue connections are configured;
- failed jobs are stored through Laravel's failed-job infrastructure;
- no custom app/Jobs directory exists yet.

This is preferable to creating jobs that merely wrap simple synchronous operations.

## Queue candidates

| Work | Queue? | Decision |
|---|---:|---|
| Email notifications | Yes | Already covered by queued notifications |
| Database notifications | Yes | Already covered by queued notifications |
| Image processing | Later | Depends on Phase 5.5 storage/image pipeline |
| Exports/reports | Yes | Add dedicated jobs when export/report features are introduced |
| Heavy search rebuilds | Later | Only when search indexing exists |
| Payment core mutation | No | Financial mutation remains synchronous, transactional, idempotent |
| Payment secondary notifications | Yes | Queue after durable financial commit |
| Moderation/support notifications | Yes | Use queued notifications |
| Audit write for critical state | No | Keep durable audit in the transaction |

## Transaction boundary

The Redis queue connection is configured with after_commit=true.

AccountActivityNotification also calls afterCommit() explicitly. This prevents a worker from processing a notification before the transaction that created/updated its underlying data has committed.

## Queue topology

Production should separate workloads by queue when volume justifies it:

- notifications;
- emails;
- media;
- reports/exports;
- secondary integrations.

A single default queue is acceptable during the early deployment stage, but queue names should become explicit before workload contention appears.

## Job requirements

Every custom queued job must define:

- idempotency behavior;
- retry/backoff policy;
- timeout;
- failure handling;
- serialization-safe payload;
- authorization boundaries;
- observability.

Jobs must receive identifiers/value objects rather than large mutable Eloquent graphs whenever possible.

## Payment rule

The webhook/payment transaction must never depend on a queue to make the financial mutation durable.

The safe sequence is:

1. validate webhook;
2. lock/idempotently mutate financial state;
3. commit;
4. dispatch secondary notification/integration work after commit.

## Result

7.3 establishes a safe queue foundation and fixes transaction ordering for queued notifications.

Custom jobs are intentionally deferred until there is a real heavy operation to execute.
