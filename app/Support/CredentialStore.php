<?php

namespace App\Support;

use Spatie\Valuestore\Valuestore;

/**
 * Stores one set of credentials (token + API version) per company, so a user
 * can keep tokens for several Wethod companies and switch between them. One
 * company is marked "active"; an explicit WETHOD_COMPANY env var overrides it.
 *
 * On-disk shape:
 *
 *   {
 *     "active": "acme",
 *     "contexts": {
 *       "acme": { "token": "…", "version": "2024-06-15" }
 *     }
 *   }
 *
 * The path is resolved lazily on every access (rather than captured up front)
 * so that an environment that changes XDG_CONFIG_HOME — chiefly the test
 * suite — is always honoured.
 */
class CredentialStore
{
    private bool $migrated = false;

    /**
     * The company whose credentials are in effect: the WETHOD_COMPANY env var
     * when set, otherwise the stored active company.
     */
    public function activeCompany(): ?string
    {
        return Credentials::env('WETHOD_COMPANY') ?? $this->store()->get('active');
    }

    /**
     * The stored credentials for a company (token + version), or an empty
     * array when none are saved. Defaults to the active company.
     *
     * @return array{token?: string, version?: string}
     */
    public function context(?string $company = null): array
    {
        $company ??= $this->activeCompany();

        if ($company === null) {
            return [];
        }

        return $this->store()->get('contexts', [])[$company] ?? [];
    }

    /**
     * Save credentials for a company and mark it active.
     */
    public function setContext(string $company, string $token, ?string $version): void
    {
        $store = $this->writableStore();

        $contexts = $store->get('contexts', []);
        $contexts[$company] = array_filter([
            'token' => $token,
            'version' => $version,
        ], fn ($value) => $value !== null && $value !== '');

        $store->put('contexts', $contexts);
        $store->put('active', $company);
    }

    /**
     * Forget a company's credentials (defaults to the active company) and
     * repoint the active company at whatever remains.
     */
    public function forget(?string $company = null): void
    {
        $company ??= $this->activeCompany();

        if ($company === null) {
            return;
        }

        $store = $this->writableStore();

        $contexts = $store->get('contexts', []);
        unset($contexts[$company]);

        $contexts === [] ? $store->forget('contexts') : $store->put('contexts', $contexts);

        if ($store->get('active') === $company) {
            $next = array_key_first($contexts);

            $next === null ? $store->forget('active') : $store->put('active', $next);
        }
    }

    /**
     * The companies that have stored credentials.
     *
     * @return list<string>
     */
    public function companies(): array
    {
        return array_keys($this->store()->get('contexts', []));
    }

    /**
     * A read-only store. Reading never touches the filesystem, so env-only
     * users don't get an empty credentials file created.
     */
    private function store(): Valuestore
    {
        $this->migrateLegacyFormat();

        return PrettyValuestore::make(Credentials::path());
    }

    /**
     * A store whose file is pre-created with owner-only permissions (dir 0700,
     * file 0600). file_put_contents preserves the permissions of an existing
     * file, so subsequent writes stay 0600.
     */
    private function writableStore(): Valuestore
    {
        $this->migrateLegacyFormat();

        $path = Credentials::path();
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        if (! file_exists($path)) {
            touch($path);
        }

        @chmod($path, 0600);

        return PrettyValuestore::make($path);
    }

    /**
     * Migrate the original flat {company, token, version} layout into the
     * per-company "contexts" structure, so existing users keep their token.
     */
    private function migrateLegacyFormat(): void
    {
        if ($this->migrated) {
            return;
        }

        $this->migrated = true;

        $path = Credentials::path();

        if (! file_exists($path)) {
            return;
        }

        $store = PrettyValuestore::make($path);

        $company = $store->get('company');

        if ($company === null || $store->has('contexts')) {
            return;
        }

        $store->put('contexts', [
            $company => array_filter([
                'token' => $store->get('token'),
                'version' => $store->get('version'),
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
        $store->put('active', $company);

        $store->forget('company');
        $store->forget('token');
        $store->forget('version');
    }
}
