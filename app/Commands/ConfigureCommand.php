<?php

namespace App\Commands;

use App\Support\Credentials;
use LaravelZero\Framework\Commands\Command;
use Spatie\Valuestore\Valuestore;

class ConfigureCommand extends Command
{
    protected $signature = 'configure {--show : Display the current configuration instead of editing it}';

    protected $description = 'Set up your Wethod API token, company and version';

    public function handle(): int
    {
        $current = Credentials::store();

        if ($this->option('show')) {
            return $this->showConfiguration($current);
        }

        $this->line('The company endpoint is the subdomain of your Wethod URL, e.g. "acme" from acme.wethod.com.');
        $company = trim((string) $this->ask('Company endpoint', $current->get('company') ?? config('wethod.company')));

        if ($company === '') {
            $this->error('A company endpoint is required.');

            return self::FAILURE;
        }

        $hasToken = $current->has('token');
        $token = (string) $this->secret(
            'API token'.($hasToken ? ' (leave blank to keep the current one)' : '')
        );

        if ($token === '' && $hasToken) {
            $token = (string) $current->get('token');
        }

        if ($token === '') {
            $this->error('An API token is required.');

            return self::FAILURE;
        }

        $version = trim((string) $this->ask('API version', $current->get('version') ?? config('wethod.version')));

        Credentials::writableStore()->put([
            'company' => $company,
            'token' => $token,
            'version' => $version,
        ]);

        $this->newLine();
        $this->info('Credentials saved to '.Credentials::path());
        $this->line('Run <comment>wethod list</comment> to see the available commands.');

        return self::SUCCESS;
    }

    private function showConfiguration(Valuestore $current): int
    {
        $token = (string) ($current->get('token') ?? config('wethod.token') ?? '');

        $this->table(['Setting', 'Value'], [
            ['Company', (string) ($current->get('company') ?? config('wethod.company') ?? '—')],
            ['Token', $token === '' ? '—' : $this->maskToken($token)],
            ['Version', (string) ($current->get('version') ?? config('wethod.version'))],
            ['Base URL', (string) config('wethod.base_url')],
            ['Credentials file', Credentials::path()],
        ]);

        return self::SUCCESS;
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) <= 4) {
            return str_repeat('*', strlen($token));
        }

        return str_repeat('*', strlen($token) - 4).substr($token, -4);
    }
}
