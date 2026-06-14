<?php

namespace App\Providers;

use App\Support\Credentials;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Spatie\OpenApiCli\Facades\OpenApiCli;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Default Wethod API version used when none is configured.
     */
    private const DEFAULT_VERSION = '2024-06-15';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Share a single HTTP client factory so global options set during
        // boot survive when the facade is re-resolved at request time.
        $this->app->singleton(HttpFactory::class);

        $this->resolveCredentials();
        $this->registerEndpointCommands();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->attachWethodHeaders();
    }

    /**
     * Merge stored credentials into config without overriding env vars.
     */
    private function resolveCredentials(): void
    {
        $creds = Credentials::store();

        config([
            'wethod.token' => config('wethod.token') ?: $creds->get('token'),
            'wethod.company' => config('wethod.company') ?: $creds->get('company'),
            'wethod.version' => config('wethod.version') ?: $creds->get('version') ?: self::DEFAULT_VERSION,
        ]);
    }

    /**
     * Turn the Wethod OpenAPI spec into `wethod:*` commands.
     */
    private function registerEndpointCommands(): void
    {
        // The registration store is static; clear it so repeated boots
        // (e.g. across the test suite) don't accumulate duplicates.
        OpenApiCli::clearRegistrations();

        OpenApiCli::register(config('wethod.spec_url'), 'wethod')
            ->baseUrl(config('wethod.base_url'))
            ->auth(fn () => config('wethod.token'))
            ->cache(ttl: config('wethod.spec_cache_ttl'))
            ->useOperationIds()
            ->banner('Wethod CLI')
            ->onError(function (Response $response, Command $command): bool {
                if ($response->status() === 429) {
                    $retryAfter = $response->header('x-ratelimit-retry-after');
                    $command->warn('Rate limited by Wethod. Retry after '.($retryAfter ?: '?').'s.');

                    return true;
                }

                return false;
            });
    }

    /**
     * Attach the headers Wethod requires on every request. The OpenAPI
     * package ignores `in: header` parameters and only sets the bearer
     * token, so the company/version headers are injected here as global
     * request options, resolved lazily so config changes are picked up.
     */
    private function attachWethodHeaders(): void
    {
        Http::globalOptions(fn (): array => [
            'headers' => array_filter([
                'Wethod-Version' => config('wethod.version'),
                'Wethod-Company' => config('wethod.company'),
            ]),
        ]);
    }
}
