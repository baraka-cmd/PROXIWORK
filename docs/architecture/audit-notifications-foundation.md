# 3.6 Audit Logging

## Purpose

Audit logs answer:

> Who did what, when, and on which resource?

They are an operational/security trail, not an application activity feed.

## Data

- `user_id`: nullable so system/anonymous security events can be recorded.
- `action`: stable machine-readable action name.
- `subject_type` / `subject_id`: affected model when applicable.
- `ip_address`: request source.
- `user_agent`: client identification, truncated to 2000 characters.
- `metadata`: structured context, never passwords, tokens, authorization headers or secrets.
- `created_at`: event timestamp.

## Privacy and retention

IP addresses and user agents can be personal data. They are therefore:

- accessible only through the `audit.view` permission;
- never exposed to normal users;
- not included in public resources;
- retained for 180 days by default;
- pruned by the scheduled `audit:prune` command;
- configurable with `AUDIT_LOG_RETENTION_DAYS`.

Do not put unnecessary PII into `metadata`.

## Immutability

There is deliberately no public update/delete endpoint for audit logs. The application only appends audit events.

Administrative retention pruning is a controlled infrastructure operation, not a user-facing CRUD action.

## Current events

The foundation records:

- register
- login
- logout
- password_changed
- password_reset
- profile_updated
- address_deleted
- role_created
- role_updated
- role_deleted

Future domain modules should add stable action names for important security/business transitions.

# 3.7 Notifications Foundation

Notifications use Laravel's Notification system.

## Channels

- Database: implemented.
- Mail: implemented through Laravel MailMessage.
- SMS: preference prepared, provider intentionally deferred.
- Push: preference prepared, provider intentionally deferred.

Notifications implement `ShouldQueue` so delivery can move to a real queue worker without changing business code.

## Preferences

Each user has one preference record controlling:

- database
- email
- SMS
- push

Database/email default to enabled; SMS/push default to disabled until providers are introduced.

## API

- GET `/api/v1/notifications`
- POST `/api/v1/notifications/{notification}/read`
- POST `/api/v1/notifications/read-all`
- GET `/api/v1/notifications/preferences`
- PATCH `/api/v1/notifications/preferences`

Notification queries are always scoped through the authenticated user's Notifiable relationship, preventing cross-user access.

## Security

Notification payloads must never contain passwords, tokens, secrets or unnecessary sensitive data.

Future high-value notifications should be event-driven and queued. Providers for SMS/push should be implemented behind Laravel Notification channels rather than coupled to controllers.
