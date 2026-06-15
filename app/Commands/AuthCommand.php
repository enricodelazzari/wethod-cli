<?php

namespace App\Commands;

use App\Support\Credentials;
use App\Support\CredentialStore;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class AuthCommand extends Command
{
    protected $signature = 'auth {--show-token : Reveal the active token instead of masking it}';

    protected $description = 'Show the active Wethod credentials and stored companies';

    public function handle(CredentialStore $credentials): int
    {
        $active = $credentials->activeCompany();
        $context = $credentials->context($active);
        $token = (string) (config('wethod.token') ?? $context['token'] ?? '');

        table(['Setting', 'Value'], [
            ['Active company', $active ?? '—'],
            ['Token', $token === '' ? '—' : ($this->option('show-token') ? $token : $this->maskToken($token))],
            ['Version', (string) config('wethod.version')],
            ['Base URL', (string) config('wethod.base_url')],
            ['Credentials file', Credentials::path()],
        ]);

        $companies = $credentials->companies();

        if ($companies === []) {
            warning('No companies configured. Run `wethod login` to add one.');

            return self::SUCCESS;
        }

        $items = array_map(
            fn (string $company) => '  - '.$company.($company === $active ? ' (active)' : ''),
            $companies,
        );

        note('Stored companies:'.PHP_EOL.implode(PHP_EOL, $items));

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
