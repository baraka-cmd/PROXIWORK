# Phase 5.6 — API Performance

## Collection controls

Public collection endpoints use validated bounded pagination.

- per_page minimum 1, maximum 100;
- query strings are preserved for pagination;
- collection responses use API Resources.

## Query payload control

High-traffic public endpoints avoid SELECT * and unconstrained relation loading.

### Professional search

The search query now selects only fields required by the resource and constrains user, profile, default address, and skills.

Published service count remains a database-side withCount aggregate.

### Service listing

The service listing now selects only resource fields and constrains category, skills, images, professional profile, and professional user.

The image relation selects only fields required by ServiceImageResource.

## N+1 protection

Resources use whenLoaded() for optional relations, while controllers/services explicitly eager-load the relations required by each endpoint.

No global eager loading is introduced.

## Pagination strategy

Length-aware pagination remains appropriate for search and catalog endpoints because clients need total pages.

Cursor pagination is reserved for very large chronological feeds where total counts are unnecessary.

## Caching

The API layer does not introduce broad response caching here. Redis caching remains a separate concern with explicit invalidation.

## Search

Leading-wildcard text search remains intentionally deferred to full-text/search-engine optimization because ordinary B-tree indexes do not solve %term% queries.

## Result

7.6 reduces database payload and relation hydration cost while preserving API contracts. Performance-sensitive changes are limited to measured query-shape improvements rather than speculative infrastructure.
