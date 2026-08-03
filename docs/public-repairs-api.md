# Public Repairs API

Exports repair records as [Open Repair Data Standard](https://openrepair.org/open-data/open-standard/)
v0.3, under `/api/public/v2`. Intended for bulk ingestion by the Open Repair Alliance rather than
for display.

## Feature flag

Enable with:

`FEATURE__PUBLIC_REPAIRS_API=true`

Independent of `FEATURE__PUBLIC_EVENTS_API`; either scope can be enabled without the other.

## Required configuration

The endpoint returns `503` until both are set:

- `ORDS_ID_PREFIX` — the partner namespace assigned by the Open Repair Alliance, e.g. `ifixit_`
- `ORDS_DATA_PROVIDER` — the organisation name shown on every published row

Neither has a default. The identifier is a stable key the consumer upserts on across releases, so
publishing under an unassigned or borrowed namespace overwrites another provider's records. Once
records have been published under a prefix it must not change.

Optional:

- `ORDS_INCLUDE_PROBLEM` (default `true`) — set `false` for a structured-fields-only export
- `ORDS_SCRUB_PROBLEM` (default `true`) — redaction pass over the `problem` column

## Authentication

`Authorization: Bearer <integration_token>`, with the `repairs:read` scope:

- `php artisan api-clients:create --name="Partner Name" --scopes=repairs:read`
- `php artisan api-clients:revoke <id>`
- `php artisan api-clients:rotate <id>`

An `events:read` token is rejected with `403`, and vice versa. Scopes are comma-separated if one
client needs both.

## Endpoint

- `GET /api/public/v2/repairs`

## Query params

- `format` — `json` (default) or `csv`
- `updated_since` (ISO8601) — devices modified at or after this time
- `event_start` (ISO8601 or date) — events starting at or after
- `event_end` (ISO8601 or date) — events starting at or before; a date-only value covers that whole day
- `powered` — `1`/`true` for powered categories, `0`/`false` for unpowered; omit for both
- `page` (default `1`)
- `per_page` (default `100`, max `1000`)

## Output

JSON carries the records in `data`, with `meta` (pagination, column list) and `sync`
(`generated_at`, `max_updated_at`) alongside.

CSV has no envelope, so the same metadata travels in headers: `X-Total-Count`, `X-Page`,
`X-Per-Page`, `X-Last-Page`, `X-Max-Updated-At`. Cells opening with `=`, `+`, `-` or `@` are
escaped, since spreadsheet software executes them on open.

## Columns

Fourteen columns in the order the standard defines.

| Column | Source |
| --- | --- |
| `id` | `ORDS_ID_PREFIX` + `devices.iddevices` |
| `data_provider` | `ORDS_DATA_PROVIDER` |
| `country` | `groups.country_code`, translated alpha-2 to alpha-3 |
| `partner_product_category` | `categories.name ~ devices.item_type` |
| `product_category` | `categories.name`, mapped to the standard's vocabulary |
| `product_category_id` | name lookup; our `idcategories` do not match the published ids |
| `brand` | `devices.brand` |
| `year_of_manufacture` | derived: event year minus `devices.age` |
| `product_age` | `devices.age`, omitted when `0` |
| `repair_status` | `devices.repair_status`, `Unknown` when unset |
| `repair_barrier_if_end_of_life` | first `barriers` row, only when end of life |
| `group_identifier` | `groups.name` |
| `event_date` | `events.event_start_utc` as a local date in the event's timezone |
| `problem` | `devices.problem`, scrubbed |

## Vocabulary notes

The maps in `config/ords.php` follow the Open Repair Alliance's published data, not its
`tableschema.json`, which is stale in two places. Where they disagree:

- the schema's `id` pattern uses a hyphen; every published row uses an underscore
- the schema's barrier enum reads `Too worn out`; the published data reads `Item too worn out`

Other differences worth knowing:

- the standard collapses our screen-size and laptop-size splits into `Flat screen` and `Laptop`
- unpowered repairs are published separately with no `product_category_id`, so those records carry
  an `Unpowered - X` name and a null id
- a category with no mapping falls back to our own name with a null id rather than being dropped

Re-check the published category list when a new release lands; unmapped categories degrade quietly.

## Redaction

`devices.problem` is unsanitised free text. The scrub strips HTML and redacts email addresses,
phone numbers, digit runs of eight or more, and URL query strings, keeping the bare URL. Counts by
type are logged per request so an export can be checked before handover.

Personal names are not pattern-detectable and are not removed.

## Defaults and visibility rules

- Returns only devices from approved events on approved groups.
- Excludes soft-deleted events and groups. Devices are hard-deleted, so there is no deleted-device case.
- Honours the client's `allowed_network_ids` when set.
- No date filter by default; the full history is returned.

## CORS/origin behavior

Same as the events API: CORS headers are returned for public API routes, and a client with
`allowed_origins` configured rejects a non-matching `Origin` with `403`.
