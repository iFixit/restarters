# Public Events API

The public events API is available under `/api/public/v2` and is intended for third-party event ingestion/display.

## Feature flag

Enable with:

`FEATURE__PUBLIC_EVENTS_API=true`

## Authentication

All `GET` endpoints require:

`Authorization: Bearer <integration_token>`

Tokens are managed via artisan commands:

- `php artisan api-clients:create --name="Partner Name"`
- `php artisan api-clients:revoke <id>`
- `php artisan api-clients:rotate <id>`

## Endpoints

- `GET /api/public/v2/events`
- `GET /api/public/v2/events/{id}`
- `GET /api/public/v2/groups/{id}/events`

## Query params (`GET /events`)

- `start` (ISO8601)
- `end` (ISO8601)
- `updated_start` (ISO8601)
- `updated_end` (ISO8601)
- `page` (default `1`)
- `per_page` (default `50`, max `100`)

## Defaults and visibility rules

- Defaults to upcoming events (`event_end_utc >= now`).
- Returns only approved events from approved groups.
- Excludes soft-deleted events/groups.
- Public payload intentionally omits `stats`, `network_data`, and `group.networks`.

## CORS/origin behavior

- CORS headers are returned for public API routes.
- If an API client has `allowed_origins` configured, requests with a non-matching `Origin` are rejected with `403`.
