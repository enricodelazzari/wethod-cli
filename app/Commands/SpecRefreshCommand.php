<?php

namespace App\Commands;

use Illuminate\Support\Facades\Cache;
use LaravelZero\Framework\Commands\Command;

class SpecRefreshCommand extends Command
{
    protected $signature = 'wethod:spec:refresh';

    protected $description = 'Clear the cached OpenAPI spec so it is re-fetched on the next run';

    public function handle(): int
    {
        $specUrl = (string) config('wethod.spec_url');

        // Matches the cache key built by Spatie\OpenApiCli\SpecResolver.
        Cache::forget('openapi-cli-spec:'.md5($specUrl));

        $this->info('Cached spec cleared. It will be re-fetched on the next command.');

        return self::SUCCESS;
    }
}
