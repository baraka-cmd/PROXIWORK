# Consolidation — Phases 1 + 2

## Objective

This review consolidates the foundation and marketplace/discovery modules before Phase 3 (transactions).

The goal is not cosmetic refactoring. The review verifies:

- responsibility boundaries;
- authorization and ownership;
- validation;
- data integrity;
- concurrency-sensitive operations;
- query shape and pagination;
- API contracts;
- test coverage;
- CI quality gates;
- architecture simplicity and future evolution.

## Baseline

Target branch: `phase-1/foundation`

Phases covered:

- Phase 1 — Foundation & Core Platform
- Phase 2 — Marketplace & Professional Discovery

Phase 3 is intentionally not introduced by this consolidation.

## Architecture review

The current Laravel flow remains appropriate:

`Route → Middleware → FormRequest → Controller → Service → Model → Database → Resource`

### Controllers

Controllers remain orchestration layers. Complex marketplace logic is kept in services.

No repository layer was introduced because Eloquent already provides the required persistence abstraction and no second persistence implementation exists.

No DTO layer was introduced because the current API resources already define the response boundary and the request payloads are simple enough to be handled by Form Requests.

### Form Requests

Requests own:

- input validation;
- normalization;
- request-level authorization where appropriate;
- cross-field validation.

Business invariants that must also hold outside HTTP remain enforced in services and/or the database.

### Services

Services own multi-step business operations and transaction boundaries.

Examples:

- address default-state changes;
- professional service creation/update/publication;
- service image cover management;
- favorites idempotency.

### Policies

Policies remain the resource-level authorization boundary for ownership-sensitive resources.

Middleware handles broad role/permission access; policies handle the resource itself.

## Corrections made

### 1. Professional service listing now explicitly uses policy authorization

The authenticated professional route already had role protection, but the controller now also calls the service policy for `viewAny`.

This keeps the authorization contract explicit and prevents the controller from depending only on the route middleware.

### 2. Image limit enforcement moved to the business service

The image controller previously checked the 8-image limit and the service checked it again.

The controller-level check created a time-of-check/time-of-use window.

The authoritative check now occurs inside a database transaction after locking the service row:

- lock service;
- count images;
- reject when already at 8;
- determine cover;
- update cover flags;
- create image.

This makes concurrent uploads safer and centralizes the business rule.

### 3. Service slug creation hardened against concurrent inserts

A unique database index remains the final integrity guarantee.

Normal creation first generates a readable slug from the title.

If a concurrent insert wins the same unique slug, the unique-constraint exception is handled and a collision suffix is generated.

The database constraint therefore remains authoritative instead of relying on a non-atomic existence check.

### 4. Favorite pagination lower bound corrected

Favorite pagination now clamps `per_page` to:

- minimum: 1
- maximum: 100

This aligns it with the other paginated endpoints.

## Security review

Verified principles:

- Sanctum protects authenticated API routes.
- Role middleware protects client/professional dashboard areas.
- Permission middleware protects administrative capabilities.
- Policies protect ownership-sensitive resources.
- Form Requests prevent mass-assignment of protected business fields.
- Public resources do not expose private account fields such as email.
- Service image upload validates file type and size.
- Public professional search only considers published services and active categories.
- Pagination and sorting inputs are bounded/whitelisted.
- Search ordering is not directly driven by arbitrary SQL input.
- Audit metadata sanitizes common credential fields.

Important rule for Phase 3:

Every transaction resource must receive the same explicit ownership/BOLA/IDOR review before merge.

## Performance review

Current safeguards include:

- eager loading on discovery results;
- `withCount` for published service counts;
- pagination with bounded page size;
- query-level filtering instead of in-memory filtering;
- N+1 regression tests;
- indexes aligned with current marketplace filters;
- no unnecessary repository/DTO layers;
- no unrestricted public service listing.

Known future scalability point:

Free-text discovery currently uses wildcard `LIKE` queries across several fields. This is acceptable for the current phase, but production-scale search should be measured before deciding between MySQL full-text search or a dedicated search engine.

## Database integrity

Important integrity constraints are enforced by the database:

- foreign keys;
- cascade/restrict behavior;
- unique favorite pair;
- unique service slug.

Application transactions protect multi-step state transitions.

Before Phase 3, transaction-sensitive rules such as orders, payments, wallet balances, quotas and idempotency keys must use the same database-first integrity approach.

## Test review

Existing Phase 1 + 2 tests cover:

- authentication;
- RBAC;
- profiles;
- addresses;
- notifications;
- audit logs;
- categories;
- skills;
- professional services;
- service images;
- professional discovery;
- favorites;
- professional dashboard;
- client dashboard;
- API contracts;
- security/ownership;
- N+1/query growth;
- pagination bounds.

This consolidation additionally covers the lower pagination boundary for favorites.

## CI review

The repository CI currently verifies:

1. Composer configuration;
2. dependency installation;
3. Laravel package discovery;
4. complete PHPUnit/Laravel test suite;
5. Laravel Pint.

The consolidation must not be considered complete until its PR CI is green.

## Architecture decisions

### Keep

- Laravel native conventions;
- Eloquent;
- Form Requests;
- Policies;
- API Resources;
- focused Services for non-trivial business operations;
- database constraints;
- feature/security tests.

### Do not introduce yet

- generic repositories;
- generic managers for every model;
- unnecessary DTOs;
- speculative event buses;
- artificial Clean Architecture layers;
- search infrastructure before measured need.

Complexity must be justified by a real business or technical requirement.

## Exit criteria before Phase 3

Phase 1 + 2 can be considered consolidated when:

- no known critical authorization defect remains;
- ownership boundaries are explicit;
- business invariants have a clear owner;
- concurrent state changes have transaction boundaries;
- pagination/filter/sort inputs are bounded;
- resources expose only intended fields;
- tests cover important security boundaries;
- CI is green;
- no unnecessary abstraction was introduced;
- the architecture remains understandable to a new developer.

## Decision

**Phase 3 should start only after this consolidation PR passes CI and is merged.**

The next phase will introduce transactions on top of this stabilized foundation rather than compensating for architectural debt later.
