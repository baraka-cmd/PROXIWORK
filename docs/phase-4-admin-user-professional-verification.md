# Phase 4 — Administration : 6.2, 6.3, 6.4

## Architectural rule

PROXIWORK keeps three independent concepts:

- **Role**: what the account is allowed to do.
- **Account status**: whether the account can currently use the platform.
- **Professional verification status**: the trust/verification state of a professional profile.

They are intentionally not represented by one field.

## 6.2 — User Management

### Account status

The users account status uses:

- active
- suspended

Suspension is not deletion. Existing marketplace, order, payment, messaging and audit records remain intact.

Suspending a user:

1. locks the user row;
2. changes account status;
3. revokes Sanctum personal access tokens;
4. records an audit event;
5. queues an account notification.

Login and authenticated application routes reject suspended accounts.

### API

- GET /api/v1/admin/users
- GET /api/v1/admin/users/{user}
- POST /api/v1/admin/users/{user}/suspend
- POST /api/v1/admin/users/{user}/activate

List filters are validated and paginated. The server controls sortable columns and page size.

## 6.3 — Professional Management

Professional administration operates on ProfessionalProfile, while account suspension/activation is applied to the related User.

The professional dossier is a consolidated read model composed from existing relations. It does not duplicate marketplace data.

The API exposes:

- professional identity and title;
- account status;
- verification status;
- availability;
- rating;
- aggregate counts;
- skills;
- verification history.

A professional can therefore be:

- verified + active;
- verified + suspended;
- rejected + active;
- under review + active.

This separation is intentional.

## 6.4 — Verification System

Verification is a domain state machine.

Allowed transitions:

PENDING -> UNDER_REVIEW

UNDER_REVIEW -> VERIFIED

UNDER_REVIEW -> REJECTED

No arbitrary status update endpoint exists.

Rejected verification is terminal in this first workflow. A future resubmission flow must be introduced explicitly rather than bypassing the state machine.

### History

Every transition creates a professional_verification_reviews record containing:

- professional profile;
- administrator;
- previous status;
- new status;
- controlled rejection reason when applicable;
- optional note;
- timestamp.

The current status and history are written in one database transaction.

The professional row is locked during a transition, preventing two administrators from successfully applying conflicting decisions to the same state.

### Rejection reasons

- DOCUMENT_INVALID
- PROFILE_INCOMPLETE
- IDENTITY_MISMATCH
- OTHER

## Authorization

Routes use the existing Sanctum + RBAC stack.

Permissions introduced:

- admin.users.view
- admin.users.suspend
- admin.users.activate
- admin.professionals.view
- admin.professionals.suspend
- admin.professionals.activate
- admin.professionals.review
- admin.professionals.verify
- admin.professionals.reject

Policies remain contextual authorization gates in addition to permission middleware.

## Performance

Administrative lists use:

- SQL-side filtering;
- bounded pagination;
- eager loading;
- aggregate counts;
- indexed account/verification status fields.

No full-table hydration is used for list endpoints.

## Audit and privacy

Existing AuditLogService is reused. Sensitive verification documents are not exposed by these resources.

Audit metadata is sanitized using the existing sensitive-key policy.

## Test strategy

The feature suite covers:

- authentication and authorization;
- search/filter/pagination;
- user suspension/activation;
- token revocation;
- suspended account enforcement;
- professional account/verification independence;
- self-suspension protection;
- valid verification transitions;
- invalid verification transitions;
- controlled rejection reasons;
- verification history;
- audit records.

CI remains the final quality gate before merge.
