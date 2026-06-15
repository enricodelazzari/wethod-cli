# CLAUDE.md

Guidance for AI coding assistants working on the **Wethod CLI** repository.

## What this is

A standalone CLI for the Wethod API, built with **Laravel Zero 12** and
**`spatie/laravel-openapi-cli`**. Every endpoint in Wethod's OpenAPI spec is
turned into its own command at boot — there is no hand-written command per
endpoint. The binary is `wethod` (run as `php wethod …` in development).

## Architecture

- **`app/Providers/AppServiceProvider.php`** is the heart of the app:
  - `registerEndpointCommands()` calls `OpenApiCli::register(...)` with the spec
    URL, base URL, auth callback, cache TTL, and an `onError` handler (401 → "run
    `wethod login`", 429 → rate-limit notice).
  - `resolveCredentials()` merges the active company's stored token/version into
    `config('wethod.*')` without overriding env vars.
  - `attachWethodHeaders()` injects the `Wethod-Company` / `Wethod-Version`
    headers as global HTTP options (the OpenAPI package ignores `in: header`
    parameters), resolved lazily so config changes are picked up.
  - Binds `WethodDescriber` to `DescriberContract` so the banner renders on
    `wethod list`.
- **Credentials** (`app/Support/`): `Credentials` holds XDG-aware paths;
  `CredentialStore` keeps one `{token, version}` per company under a `contexts`
  map in `~/.config/wethod/credentials.json` (0600), tracks the `active`
  company, honours `WETHOD_COMPANY`, and migrates the old flat format. The path
  is resolved lazily on every access so tests can repoint `XDG_CONFIG_HOME`.
- **Auth commands** (`app/Commands/`): `login` (validates the token against
  `GET /api/clients?limit=1` — a 401 is rejected, anything else accepted),
  `logout`, `auth`. `RequireCredentials` (a `CommandStarting` listener) blocks
  generated `EndpointCommand`s when no credentials are configured.
- **Config**: `config/wethod.php` (spec URL, base URL, version, cache TTL).
  Env vars `WETHOD_*` override everything.

## Agent skill

`skills/wethod/` is a Claude-installable skill teaching assistants to drive the
CLI. `wethod install-skill [--global]` copies it into `.claude/skills/wethod`.
The skill favours runtime discovery (`wethod list`, `wethod <cmd> --help`) over a
hardcoded command list, because commands are generated from the live spec.

> **Bundling rule:** any non-`app` asset that must ship inside the compiled PHAR
> (the `skills/` directory) **must** be listed in `box.json` `directories`.

## Gotchas

- Laravel Zero disables package auto-discovery: service providers go in both
  `bootstrap/providers.php` and `config/app.php`.
- `OpenApiCli::clearRegistrations()` is called before registering so repeated
  boots (the test suite) don't accumulate duplicate commands.
- Tests point `WETHOD_SPEC_URL` at `tests/Fixtures/mini-openapi.yaml` (see
  `tests/Pest.php`) so the suite never hits the network. Mock API calls with
  `Http::fake()`.

## Commands

```bash
./vendor/bin/pest               # tests
./vendor/bin/pint               # format
./vendor/bin/phpstan analyse    # static analysis (level 5, Larastan)
php wethod list                 # verify the real command set
php wethod app:build            # build the standalone PHAR
```

When documenting or changing command behaviour, verify against the actual
binary with `php wethod list` and `php wethod <command> --help`.
