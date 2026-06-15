<?php

namespace App\Listeners;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Spatie\OpenApiCli\Commands\EndpointCommand;

class RequireCredentials
{
    /**
     * Human-readable labels for each required credential, keyed by the
     * `wethod.*` config value that must be present.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'company' => 'company',
        'token' => 'API token',
    ];

    public function __construct(private Application $app) {}

    /**
     * Stop API commands from running when credentials are missing, so the
     * user gets a clear hint instead of a confusing error from the server.
     */
    public function __invoke(CommandStarting $event): void
    {
        // Help output must work without credentials.
        if ($event->input->hasParameterOption(['--help', '-h'], true)) {
            return;
        }

        // Only the auto-generated API commands authenticate; leave login,
        // logout, auth, spec:refresh, list, help and friends alone.
        $command = $this->app->make(Kernel::class)->all()[$event->command] ?? null;

        if (! $command instanceof EndpointCommand) {
            return;
        }

        $missing = array_keys(array_filter(
            self::LABELS,
            fn (string $label, string $key) => ! config("wethod.{$key}"),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($missing === []) {
            return;
        }

        $labels = implode(', ', array_map(fn (string $key) => self::LABELS[$key], $missing));

        abort(1, "No Wethod credentials configured: {$labels}. Run `wethod login` (or set WETHOD_COMPANY / WETHOD_TOKEN).");
    }
}
