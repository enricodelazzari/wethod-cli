<?php

use App\Support\Credentials;
use App\Support\CredentialStore;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->configHome = sys_get_temp_dir().'/wethod-test-'.uniqid();
    putenv('XDG_CONFIG_HOME='.$this->configHome);
});

afterEach(function () {
    putenv('XDG_CONFIG_HOME');

    if (is_dir($this->configHome)) {
        @unlink($this->configHome.'/wethod/credentials.json');
        @rmdir($this->configHome.'/wethod');
        @rmdir($this->configHome);
    }
});

it('validates the token, stores it per company with owner-only permissions', function () {
    Http::fake(['api.wethod.com/api/clients*' => Http::response([], 200)]);

    $this->artisan('login')
        ->expectsQuestion('Company endpoint', 'acme')
        ->expectsQuestion('API token', 'tok_secret_123')
        ->expectsQuestion('API version', '2024-06-15')
        ->assertExitCode(0);

    $path = Credentials::path();

    expect($path)->toStartWith($this->configHome)
        ->and(is_file($path))->toBeTrue();

    $data = json_decode((string) file_get_contents($path), true);

    expect($data)->toMatchArray([
        'active' => 'acme',
        'contexts' => ['acme' => ['token' => 'tok_secret_123', 'version' => '2024-06-15']],
    ]);

    expect(substr(sprintf('%o', fileperms($path)), -4))->toBe('0600');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tok_secret_123')
        && $request->hasHeader('Wethod-Company', 'acme'));
});

it('rejects a token the API answers with 401, without storing anything', function () {
    Http::fake(['api.wethod.com/*' => Http::response([], 401)]);

    $this->artisan('login')
        ->expectsQuestion('Company endpoint', 'acme')
        ->expectsQuestion('API token', 'bad-token')
        ->expectsQuestion('API version', '2024-06-15')
        ->expectsOutputToContain('rejected')
        ->assertExitCode(1);

    expect(is_file(Credentials::path()))->toBeFalse();
});

it('accepts a 403 (valid token without permission)', function () {
    Http::fake(['api.wethod.com/*' => Http::response([], 403)]);

    $this->artisan('login')
        ->expectsQuestion('Company endpoint', 'acme')
        ->expectsQuestion('API token', 'tok_no_perms')
        ->expectsQuestion('API version', '2024-06-15')
        ->assertExitCode(0);

    expect(app(CredentialStore::class)->companies())->toContain('acme');
});
