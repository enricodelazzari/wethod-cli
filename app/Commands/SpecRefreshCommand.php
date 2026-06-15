<?php

namespace App\Commands;

use Illuminate\Support\Facades\Cache;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\info;

class SpecRefreshCommand extends Command
{
    protected $signature = 'spec:refresh';

    protected $description = 'Clear the cached OpenAPI spec so it is re-fetched on the next run';

    public function handle(): int
    {
        $specUrl = (string) config('wethod.spec_url');

        // Matches the cache key built by Spatie\OpenApiCli\SpecResolver.
        Cache::forget('openapi-cli-spec:'.md5($specUrl));

        info('Cached spec cleared. It will be re-fetched on the next command.');

        return self::SUCCESS;
    }
}
