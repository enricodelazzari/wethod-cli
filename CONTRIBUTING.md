# Contributing

Thanks for your interest in improving the Wethod CLI! Contributions of all
kinds are welcome — bug reports, documentation fixes, and pull requests.

## Reporting issues

- Search the [existing issues](https://github.com/enricodelazzari/wethod-cli/issues)
  first to avoid duplicates.
- Open a [bug report](https://github.com/enricodelazzari/wethod-cli/issues/new/choose)
  with the command you ran, what you expected, what happened, and the output of
  `wethod --version` plus your OS.
- For **security vulnerabilities**, do **not** open a public issue — see
  [SECURITY.md](SECURITY.md).

## Development setup

You'll need **PHP 8.4+** and **Composer**.

```bash
git clone https://github.com/enricodelazzari/wethod-cli.git
cd wethod-cli
composer install
php wethod list   # verify the command set
```

Run the CLI from source with `php wethod <command>`.

## How the CLI is structured

Commands are **not** hand-written per endpoint. At boot,
`app/Providers/AppServiceProvider.php` hands Wethod's OpenAPI spec to
[`spatie/laravel-openapi-cli`](https://github.com/spatie/laravel-openapi-cli),
which generates one command per endpoint. As a result, most changes target:

- `app/Providers/AppServiceProvider.php` — registration, credentials, headers,
  error handling
- `app/Support/` — credential storage and paths
- `app/Commands/` — the hand-written auth/utility commands (`login`, `logout`, etc.)

See [CLAUDE.md](CLAUDE.md) for a deeper architecture overview.

## Quality gate

Before opening a pull request, make sure all of the following pass:

```bash
./vendor/bin/pest               # tests
./vendor/bin/pint               # code style (Laravel Pint)
./vendor/bin/phpstan analyse    # static analysis (level 5, Larastan)
```

The test suite points `WETHOD_SPEC_URL` at a local fixture and never hits the
network — mock API calls with `Http::fake()`.

## Pull requests

- Branch off `main` and keep each PR focused on a single change.
- Add or update tests for any behaviour you change.
- Update [CHANGELOG.md](CHANGELOG.md) under the `## [Unreleased]` section.
- Make sure the quality gate above is green.

Thanks for contributing! 💙
