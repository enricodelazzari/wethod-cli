<?php

namespace App\Commands;

use App\Concerns\RendersBanner;
use App\Support\Credentials;
use App\Support\CredentialStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class LoginCommand extends Command
{
    use RendersBanner;

    protected $signature = 'login';

    protected $description = 'Store a Wethod API token for a company';

    public function handle(CredentialStore $credentials): int
    {
        $this->renderBanner($this->output);

        $company = trim(text(
            label: 'Company endpoint',
            placeholder: 'acme',
            default: $credentials->activeCompany() ?? '',
            required: 'A company endpoint is required.',
            hint: 'The subdomain of your Wethod URL, e.g. "acme" from acme.wethod.com.',
        ));

        $token = trim(password(
            label: 'API token',
            required: 'An API token is required.',
            hint: 'Create one in your Wethod Account settings.',
        ));

        $current = $credentials->context($company);
        $version = trim(text(
            label: 'API version',
            default: $current['version'] ?? config('wethod.default_version'),
        ));

        // Send the validation request with the company/version the user just
        // entered so the globally-attached Wethod headers match this attempt.
        config(['wethod.company' => $company, 'wethod.version' => $version]);

        try {
            $response = Http::withToken($token)
                ->get(rtrim((string) config('wethod.base_url'), '/').'/api/clients', ['limit' => 1]);
        } catch (ConnectionException) {
            error('Could not reach Wethod. Check your connection and try again.');

            return self::FAILURE;
        }

        // A 401 means the token is wrong; anything else (including a 403 from a
        // token without crm.view) proves the token was at least recognised.
        if ($response->status() === 401) {
            error('That API token was rejected by Wethod.');

            return self::FAILURE;
        }

        $credentials->setContext($company, $token, $version);

        info("Logged in to {$company}.");
        note('Credentials saved to '.Credentials::path().PHP_EOL.'Run `wethod list` to see the available commands.');

        return self::SUCCESS;
    }
}
