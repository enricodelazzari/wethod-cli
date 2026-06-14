# Wethod CLI

A standalone command-line client for the [Wethod API](https://docs.wethod.com/getting-started), built with
[Laravel Zero](https://laravel-zero.com) and [spatie/laravel-openapi-cli](https://github.com/spatie/laravel-openapi-cli).

Every endpoint in the Wethod [OpenAPI spec](https://docs.wethod.com/specs/openapi.yaml) becomes its own
`wethod:*` command. Authentication and the required `Wethod-Company` / `Wethod-Version` headers are added to
every request automatically.

## Requirements

- PHP 8.2+
- Composer

## Install

```bash
composer install
```

This creates the `wethod` executable in the project root. Run it with `php wethod` (or `./wethod`).

## Configure

Store your credentials once:

```bash
php wethod wethod:configure
```

You'll be asked for:

- **Company endpoint** — the subdomain of your Wethod URL (e.g. `acme` from `acme.wethod.com`)
- **API token** — a personal token from your Wethod *Account settings*
- **API version** — defaults to `2024-06-15`

Credentials are saved to `~/.config/wethod/credentials.json` (readable only by you). Review them with:

```bash
php wethod wethod:configure --show
```

Environment variables override the stored values when set: `WETHOD_TOKEN`, `WETHOD_COMPANY`,
`WETHOD_VERSION`, `WETHOD_BASE_URL`, `WETHOD_SPEC_URL`.

## Usage

List every available command:

```bash
php wethod wethod:list
```

Examples:

```bash
# Query parameters become options
php wethod wethod:list-clients --limit=10

# Path parameters become options
php wethod wethod:get-client --id=42

# Request bodies: raw JSON...
php wethod wethod:approve-budget --id=123 --input='{"comment":"Looks good"}'

# ...or repeated key=value fields
php wethod wethod:create-budget-area --field name="Production" --field budget_id=5
```

Output options: `--json`, `--yaml`, `--minify`, `-H` (include response headers). Pass `-vvv` to print the
outgoing request (method, URL, headers, body) for debugging.

## Maintenance

The OpenAPI spec is fetched once and cached. Force a refresh with:

```bash
php wethod wethod:spec:refresh
```

## Development

```bash
./vendor/bin/pest      # run the test suite
./vendor/bin/pint      # format code
php wethod app:build   # build a standalone PHAR
```
