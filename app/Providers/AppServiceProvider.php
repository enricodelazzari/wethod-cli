<?php

namespace App\Providers;

use App\Listeners\RequireCredentials;
use App\Support\CredentialStore;
use App\Support\WethodDescriber;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;
use Spatie\OpenApiCli\Facades\OpenApiCli;

use function Laravel\Prompts\warning;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Share a single HTTP client factory so global options set during
        // boot survive when the facade is re-resolved at request time.
        $this->app->singleton(HttpFactory::class);

        $this->app->singleton(CredentialStore::class);

        $this->resolveCredentials();
        $this->registerEndpointCommands();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->attachWethodHeaders();

        // Render the Wethod banner on the default `list`/summary screen.
        $this->app->singleton(DescriberContract::class, WethodDescriber::class);

        Event::listen(CommandStarting::class, RequireCredentials::class);
    }

    /**
     * Merge stored credentials into config without overriding env vars. The
     * active company (WETHOD_COMPANY env, else the stored active one) decides
     * which token/version are used.
     */
    private function resolveCredentials(): void
    {
        $store = $this->app->make(CredentialStore::class);

        $company = $store->activeCompany();
        $context = $store->context($company);

        config([
            'wethod.company' => $company,
            'wethod.token' => config('wethod.token') ?: ($context['token'] ?? null),
            'wethod.version' => config('wethod.version') ?: ($context['version'] ?? null) ?: config('wethod.default_version'),
        ]);
    }

    /**
     * Turn the Wethod OpenAPI spec into top-level commands.
     */
    private function registerEndpointCommands(): void
    {
        // The registration store is static; clear it so repeated boots
        // (e.g. across the test suite) don't accumulate duplicates.
        OpenApiCli::clearRegistrations();

        OpenApiCli::register(config('wethod.spec_url'))
            ->baseUrl(config('wethod.base_url'))
            ->auth(fn () => config('wethod.token'))
            ->cache(ttl: config('wethod.spec_cache_ttl'))
            ->useOperationIds()
            ->banner('Wethod CLI')
            ->onError(function (Response $response): bool {
                if ($response->status() === 401) {
                    warning('Authentication failed. Run `wethod login` (or check WETHOD_TOKEN).');

                    return true;
                }

                if ($response->status() === 429) {
                    $retryAfter = $response->header('x-ratelimit-retry-after');
                    warning('Rate limited by Wethod. Retry after '.($retryAfter ?: '?').'s.');

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
