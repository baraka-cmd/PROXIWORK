# PROXIWORK API Foundation

## 3.4.1 — Response contract

### Success

All successful JSON endpoints follow:

```json
{
  "data": {},
  "message": "Human-readable message.",
  "meta": {}
}
```

For paginated collections, `data` is an array and Laravel pagination metadata is returned in `meta`. Pagination links may also be present.

`success` is deliberately not part of the public contract. HTTP status already expresses success/failure.

### Errors

All API errors follow:

```json
{
  "message": "Human-readable error.",
  "errors": {}
}
```

Validation errors use field names inside `errors`.

Internal exception details, SQL messages, stack traces and secrets are never exposed.

## 3.4.2 — HTTP status strategy

- **200 OK**: successful read/update/action returning a representation.
- **201 Created**: successful resource creation.
- **204 No Content**: successful deletion or action with no representation.
- **400 Bad Request**: malformed request that cannot be interpreted as a valid API request.
- **401 Unauthorized**: missing or invalid authentication.
- **403 Forbidden**: authenticated caller lacks permission or ownership.
- **404 Not Found**: resource/route does not exist.
- **409 Conflict**: valid request conflicts with current resource state or a uniqueness/concurrency rule.
- **422 Unprocessable Content**: syntactically valid request with invalid business/input data.
- **429 Too Many Requests**: rate limit exceeded.
- **500 Internal Server Error**: unexpected server failure; response contains no internal details.

Do not use 200 for deletion. Deletion returns 204.

## 3.4.3 — Resources

Resources currently control exposed API representations for:

- User
- Profile
- Address
- Role
- Permission
- ProfessionalProfile foundation

Sensitive fields such as passwords and tokens are never exposed through UserResource.

Public marketplace representations must later use dedicated public resources rather than reusing owner/admin resources.

## 3.4.4 — Error handling

API exceptions are normalized in `bootstrap/app.php`.

Handled contracts include:

- validation → 422;
- authentication → 401;
- authorization → 403;
- model not found → 404;
- HTTP conflicts/rate limits/bad requests → corresponding 409/429/400;
- unexpected exceptions → 500 with generic message.

Application code should not leak implementation details.

## 3.4.5 — Versioning

The public API is currently:

`/api/v1/...`

Versioning is route-based. Future breaking contracts must be introduced under `/api/v2/...` rather than silently changing `v1`.

Non-breaking additions should remain backward compatible.

## 3.4.6 — Contract tests

Feature tests verify:

- response envelope;
- HTTP status codes;
- validation error shape;
- authentication/authorization failures;
- pagination metadata;
- resource field exposure;
- 204 deletion behavior;
- rate-limit behavior.

# 3.5 — Security foundation

## Authentication

Sanctum bearer tokens are used for the API. Token expiration is configured in minutes and token creation uses the same configured lifetime.

Password changes and password resets revoke existing tokens. Passwords are hashed by Laravel.

Email verification uses Laravel's signed verification mechanism.

## Authorization

Authorization is layered:

1. authentication with Sanctum;
2. RBAC permissions;
3. policies for resource ownership and object-level authorization;
4. request validation.

This protects against IDOR/BOLA.

## Input security

Validated Form Requests are used for writes. Server-owned fields are not mass assignable.

SQL access uses Eloquent/query bindings rather than string-built SQL.

Resources prevent accidental sensitive-field exposure.

## Rate limiting

- authentication: IP + normalized email;
- sensitive operations: IP + user/email;
- authenticated API: per-user, falling back to IP for guests.

Limits are intentionally conservative foundations and can later be tuned from production telemetry.

## CORS

CORS is explicitly configured for API paths.

Allowed origins are environment-driven through `CORS_ALLOWED_ORIGINS`.

Credentials are disabled by default because this API uses bearer authentication.

Never use wildcard origins together with credentials.

## Security headers

API responses receive:

- X-Content-Type-Options: nosniff
- X-Frame-Options: DENY
- Referrer-Policy: strict-origin-when-cross-origin
- restrictive Permissions-Policy
- HSTS on HTTPS responses

## Remaining security work

This foundation does not claim all application security is complete. Future reviews must still cover:

- upload/content security;
- SSRF and external URL validation where such features are introduced;
- HTML sanitization if rich content is allowed;
- audit logging;
- abuse detection;
- password policy hardening;
- token ability/scopes where needed;
- production CORS allowlist;
- HTTPS enforcement at infrastructure level;
- dependency and container scanning;
- security regression tests.
