# Command reference

The `wethod` command set is generated from Wethod's OpenAPI spec. Each API
operation becomes one command, named after its `operationId` in kebab-case
(e.g. `listClients` → `list-clients`, `approveBudget` → `approve-budget`).

Run `wethod list` for the current set, and `wethod <command> --help` for the
exact options a given command accepts. The notes below describe the shape that
is true for **all** generated commands.

## Built-in (non-API) commands

| Command | Purpose |
| --- | --- |
| `wethod login` | Store an API token for a company and mark it active. Interactive (asks for a secret) — a human must run it. |
| `wethod logout [company]` | Forget stored credentials for a company (defaults to the active one). |
| `wethod auth [--show-token]` | Show the active company, masked token, version, base URL, and all stored companies. |
| `wethod spec:refresh` | Clear the cached OpenAPI spec so it is re-fetched. |
| `wethod install-skill [--global]` | (Re)install this skill. |
| `wethod list` | List every command. |

## Anatomy of a generated API command

```
wethod <command> [--<path-or-query-param>=value ...] [body] [output flags]
```

### Parameters

- **Path parameters** (e.g. the `{id}` in `/api/clients/{id}`) become required
  options: `wethod get-client --id=42`.
- **Query parameters** become optional options: `--limit`, `--offset`,
  `--updated_at`, etc. Check `--help` for which a command supports.

### Request bodies

Two interchangeable ways to send a JSON body:

- Raw JSON: `--input='{"name":"Acme","status":"active"}'`
- Repeated fields: `--field name="Acme" --field status="active"`

Use `--input` when you already have a JSON object; use `--field` for a few
scalar values. Nested structures require `--input`.

### Output flags

| Flag | Effect |
| --- | --- |
| `--json` | JSON output (use this when parsing programmatically). |
| `--yaml` | YAML output. |
| `--minify` | Compact output. |
| `-H` | Include HTTP response headers. |
| `-vvv` | Print the outgoing request (method, URL, headers, body). |

## Authentication & headers

Every API request automatically carries the bearer token plus the
`Wethod-Company` and `Wethod-Version` headers for the active company. Override
the active company for a single invocation with the `WETHOD_COMPANY` env var
(and `WETHOD_TOKEN` / `WETHOD_VERSION` if needed):

```bash
WETHOD_COMPANY=acme wethod list-clients --json
```

## Common domains

The spec covers clients, contacts, budgets, budget areas, business units,
custom fields, and payroll. Always confirm exact command names with
`wethod list` before using them.
