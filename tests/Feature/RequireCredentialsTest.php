<?php

use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Exceptions\ConsoleException;

it('aborts an API command when credentials are missing, without sending a request', function () {
    config(['wethod.token' => null, 'wethod.company' => null]);

    Http::fake();

    try {
        $this->artisan('list-clients')->run();
        $this->fail('Expected the command to abort.');
    } catch (ConsoleException $e) {
        expect($e->getExitCode())->toBe(1)
            ->and($e->getMessage())->toContain('wethod configure');
    }

    Http::assertNothingSent();
});

it('does not interfere when credentials are present', function () {
    config([
        'wethod.token' => 'secret-token',
        'wethod.company' => 'acme',
        'wethod.version' => '2024-06-15',
    ]);

    Http::fake([
        'api.wethod.com/*' => Http::response([], 200),
    ]);

    $this->artisan('list-clients')->assertExitCode(0);

    Http::assertSent(fn ($request) => $request->hasHeader('Wethod-Company', 'acme'));
});

it('does not block configure when credentials are missing', function () {
    config(['wethod.token' => null, 'wethod.company' => null]);

    $this->artisan('configure --show')->assertExitCode(0);
});
