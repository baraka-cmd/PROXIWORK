# Phase 4 — 6.1 Admin Dashboard

## Scope

The admin dashboard is a read-only aggregation layer over existing PROXIWORK domains. It does not reimplement user, marketplace, order, payment, or commission business rules.

## Access

The endpoint requires the `admin.dashboard.view` permission through the existing RBAC system and is protected by the existing authenticated API middleware.

## Endpoint

`GET /api/v1/admin/dashboard`

Optional query parameters:
- `from`: start datetime;
- `to`: end datetime.

When omitted, the period defaults to the last 30 days ending at the current application time.

## Data contract

- users: historical total and new users in the selected period;
- professionals: historical total, new professionals, and current verification distribution;
- clients: historical total and new clients;
- services, requests, orders and payment transactions: status counts for the selected period;
- financial data: successful payment volume and posted commissions grouped by currency.

Financial values are grouped by currency and are never combined across currencies.

## Security

The dashboard is read-only. Authorization is enforced through the existing RBAC permission and the Form Request. No user-controlled role, status, financial, or ownership field is accepted.

## Performance

Aggregations are performed in SQL using COUNT, SUM and GROUP BY. The implementation does not load complete datasets into PHP memory and does not introduce a dashboard statistics table or cache in V1.

## Deliberate exclusions

Account active/suspended statistics are not invented because the current User model does not expose an account-status field.

Revenue is not represented by one ambiguous number. Payment volume, posted commission and professional net amounts are separated and grouped by currency.
