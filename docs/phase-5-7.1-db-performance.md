# Phase 5.1 — Database Performance Baseline

## Objective

Optimize database performance from measured query patterns rather than adding indexes indiscriminately.

## Baseline

The schema contains 45 migration files covering authentication, RBAC, profiles, marketplace, transactions, messaging, wallets, reviews, moderation, support, and audit.

The existing schema already has strong coverage for:

- foreign-key joins;
- pivot-table lookups;
- service/request/order status lists;
- payment idempotency and provider references;
- wallet and commission lookups;
- conversation/message retrieval;
- review lists;
- moderation queues;
- support queues;
- audit resource/actor/date access patterns.

## High-frequency query candidates

### Services

Professional discovery repeatedly filters services by:

- professional_profile_id;
- status;
- published_at.

The previous index covered only the first two columns. It was replaced with:

professional_profile_id, status, published_at

This also preserves the leftmost-prefix use case for queries filtering only by professional and status.

### Addresses

Professional discovery resolves a user's default address and may additionally filter by city/province.

The previous index:

user_id, is_default

was replaced with:

user_id, is_default, city, province

The previous access pattern remains covered by the leftmost prefix.

### Users

The admin user list commonly combines account status filtering with creation-date sorting.

The previous single-column index:

account_status

was replaced with:

account_status, created_at

## Intentionally not indexed yet

### Wildcard text search

Professional search currently uses patterns such as:

LIKE '%term%'

on professional titles, profiles, services, and skills.

A normal B-tree index is not the correct optimization for a leading-wildcard search. This is deferred to the search/API performance work where full-text/search-engine strategy can be evaluated.

### Additional composite indexes

Indexes for every possible combination of:

- verification;
- availability;
- rating;
- category;
- price;
- location;

are deliberately not added yet.

They require production-like cardinality and EXPLAIN/ANALYZE evidence before increasing write and storage costs.

## Measurement requirement

For staging/production MySQL, the following must be captured before and after major DB changes:

- EXPLAIN plans;
- examined rows;
- chosen/possible indexes;
- execution time;
- query frequency;
- pagination behavior.

Laravel query listeners can expose executed SQL and timing, while MySQL EXPLAIN should validate the actual optimizer plan.

## N+1 / eager loading review

Professional discovery already uses controlled eager loading for:

- user/profile;
- default address;
- skills;

and uses constrained withCount for published services.

The next review should verify actual query counts for the main collection endpoints rather than adding global eager loading.

## Pagination

Large admin and public collections already use pagination with bounded per_page values.

Cursor pagination remains a candidate for very large chronological feeds after workload measurement.

## Result

7.1 is implemented incrementally:

1. inventory existing indexes;
2. map real query patterns;
3. replace only high-confidence redundant/narrow indexes;
4. add structural regression tests;
5. defer speculative indexes until production-like measurement.
