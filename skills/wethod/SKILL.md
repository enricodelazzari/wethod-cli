---
name: wethod
description: >-
  Manage Wethod project, budget, client, business-unit and CRM data through the
  `wethod` CLI. Use when the user wants to list, inspect, create, update or
  approve Wethod records (clients, contacts, budgets, budget areas, business
  units, custom fields, payroll), check or refresh credentials, or otherwise
  talk to a Wethod company from the command line.
license: MIT
metadata:
  author: wethod
  version: "0.1.0"
---

# Wethod CLI

The `wethod` CLI exposes every endpoint of the Wethod API as its own command.
The command set is generated from Wethod's OpenAPI spec, so **discover commands
at runtime** rather than assuming a fixed list.

## First steps

1. Confirm the user is authenticated: `wethod auth`. If no company is active,
   tell them to run `wethod login` (it asks for a company subdomain, an API
   token, and an API version). You cannot do this for them — it needs a secret.
2. List the available commands: `wethod list`.
3. Inspect any command before using it: `wethod <command> --help`. The help
   lists the options that map to path/query parameters and request-body fields.

## Conventions (apply to every API command)

- **Path & query parameters become options**: `wethod get-client --id=42`,
  `wethod list-clients --limit=10 --offset=0`.
- **Request bodies** are supplied either as raw JSON via `--input` or as
  repeated `--field key=value` pairs:
  - `wethod create-budget-area --field name="Production" --field budget_id=5`
  - `wethod approve-budget --id=123 --input='{"comment":"Looks good"}'`
- **Output**: default is human-readable. Use `--json` for machine-readable
  output (prefer this when you will parse the result), `--yaml`, `--minify`,
  and `-H` to include response headers. `-vvv` prints the outgoing request for
  debugging.
- **Pagination**: list endpoints take `--limit` (max 100) and `--offset`. To
  read everything, page until a response returns fewer than `--limit` items.

## Output handling

Always pass `--json` when you need to extract a value, then parse with `jq`:

```bash
wethod list-clients --limit=1 --json | jq '.[0].id'
```

## Date and calendar conventions

- **Weeks run Monday → Friday**. When the user asks for "this week" or "la
  settimana corrente", compute dates from the most recent Monday through the
  coming Friday. Never assume Sunday is the first day of the week.
- Always express dates as `YYYY-MM-DD` in API calls.

## Known API quirks

- **`--field` sends strings**: `--field project_id=13727` sends the value as a
  string, which fails for integer fields. Always use `--input` with raw JSON
  when the body contains integers, floats, or enums:
  ```bash
  wethod create-timesheet --input='{"project_id":13727,"person_id":617,"date":"2026-06-18","hours":4,"mode":"DAILY"}'
  ```
- **Enum values are uppercase in input**: fields like `mode` on timesheets
  accept `DAILY` / `WEEKLY` (uppercase) even though the API returns them as
  `daily` / `weekly` in responses. When in doubt, check the OpenAPI spec cached
  at `~/.config/wethod/cache/` or run `wethod spec:refresh` to re-fetch it.
- **`get-authenticated-person` may return empty name fields**: `first_name` and
  `last_name` can both be empty strings even for a valid session. Use the
  `email` field to identify the user, and the `id` field for subsequent API
  calls.
- **`hours` is integer for planning but float for timesheets**: people
  allocations (planning) report `hours` as an integer (0–8); timesheet hours are
  floats (e.g. `15.6`). When you sum hours, do it in `jq` (`map(.hours) | add`),
  not with bash integer arithmetic (`$((a + b))`) — the latter silently
  truncates or errors on fractional timesheet hours.

## Errors

- "No Wethod credentials configured" / a 401 warning → the user must run
  `wethod login`. Do not retry blindly.
- A 429 warning means rate-limited; wait the suggested seconds before retrying.

See `references/commands.md` for the command anatomy and `references/workflows.md`
for multi-step recipes.
