# Phase 5.2 — Cache / Redis Strategy

## Current state

Laravel already exposes a dedicated Redis cache store through:

- config/cache.php -> redis;
- REDIS_CLIENT=phpredis;
- Redis cache connection separated from the default Redis connection;
- environment-controlled cache driver selection.

The application does not force Redis as the default cache driver yet.

This is intentional: local development and CI remain independent of a running Redis server.

## Cache decision matrix

| Data | Cache? | Decision |
|---|---:|---|
| Active category catalog | Yes, candidate | Stable/read-heavy; explicit invalidation on admin mutation |
| Active skill catalog | Yes, candidate | Stable/read-heavy; explicit invalidation on admin mutation |
| Permissions / authorization decisions | Not application-cached yet | Security-sensitive; avoid stale authorization |
| Popular professionals | Later | Requires a measured ranking/query and short TTL |
| Full professional search | Not yet | High-dimensional keys and complex invalidation |
| Financial/payment state | No | Source of truth remains MySQL |
| User-specific private data | No by default | Avoid cross-user leakage |
| Configuration | Framework cache | Use deployment-time config caching rather than Redis |
| Rate limiting | Production candidate | Redis is appropriate for distributed rate limits |

## Redis usage rules

1. Cache only read-heavy data whose source of truth remains the database.
2. Every mutable cached dataset must have an explicit invalidation strategy.
3. Never cache authorization decisions without a deliberate invalidation model.
4. Never cache financial state as a source of truth.
5. Cache keys must include the dataset version/scope when required.
6. TTLs must be finite unless the dataset is truly immutable.
7. Production Redis must be shared by application instances.
8. Tests must not require a live Redis server.

## Deferred runtime caching

### Categories and skills

These are the strongest candidates because their public active catalogs change infrequently.

Before introducing runtime caching, the endpoint contract must be separated between:

- unfiltered/default catalog requests;
- filtered/search requests;
- admin mutations.

The cache should not blindly serialize arbitrary paginated query builders.

### Professional search

The current search combines:

- text search;
- category;
- multiple skills;
- city/province;
- price;
- rating;
- availability;
- verification;
- multiple sort strategies.

A naive cache key based on the entire query string would create a very large key space and difficult invalidation. Search caching is therefore deferred until query frequency and cardinality are measured.

### Permissions

Authorization is security-sensitive. A stale permission cache could grant or deny access incorrectly. Permission caching will only be introduced with explicit invalidation tied to role/permission mutations.

## Operational Redis configuration

Production should use:

- PhpRedis where available;
- a dedicated Redis cache database/namespace;
- a stable CACHE_PREFIX;
- authenticated/TLS Redis when the infrastructure requires it;
- monitoring for memory usage, evictions, hit rate, connection errors, and latency.

## Result

7.2 establishes Redis as the supported shared-cache infrastructure and defines safe cache targets.

No broad runtime cache has been added yet because the measured workload does not justify the invalidation complexity. The next cache implementation should start with the active category/skill catalogs if production-like traffic confirms they are hot.
