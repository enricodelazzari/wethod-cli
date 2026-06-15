<?php

use Illuminate\Support\Facades\Http;

it('sends the bearer token and required Wethod headers', function () {
    config([
        'wethod.token' => 'secret-token',
        'wethod.company' => 'acme',
        'wethod.version' => '2024-06-15',
    ]);

    Http::fake([
        'api.wethod.com/*' => Http::response([], 200),
    ]);

    $this->artisan('list-clients')->assertExitCode(0);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request->hasHeader('Wethod-Company', 'acme')
            && $request->hasHeader('Wethod-Version', '2024-06-15');
    });
});

it('registers a command for each operation in the spec', function () {
    $this->artisan('list-clients', ['--help' => true])->assertExitCode(0);
});
