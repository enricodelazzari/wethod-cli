<?php

namespace App\Commands;

use App\Support\CredentialStore;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

class LogoutCommand extends Command
{
    protected $signature = 'logout {company? : The company to forget (defaults to the active one)}';

    protected $description = 'Remove the stored credentials for a company';

    public function handle(CredentialStore $credentials): int
    {
        $companies = $credentials->companies();

        if ($companies === []) {
            info('No credentials are stored.');

            return self::SUCCESS;
        }

        $company = $this->argument('company') ?? select(
            label: 'Which company do you want to log out of?',
            options: $companies,
            default: $credentials->activeCompany(),
        );

        $credentials->forget($company);

        info("Logged out of {$company}.");

        return self::SUCCESS;
    }
}
