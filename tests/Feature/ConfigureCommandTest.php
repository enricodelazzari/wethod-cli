<?php

use App\Support\Credentials;

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

it('writes credentials to disk with owner-only permissions', function () {
    $this->artisan('configure')
        ->expectsQuestion('Company endpoint', 'acme')
        ->expectsQuestion('API token', 'tok_secret_123')
        ->expectsQuestion('API version', '2024-06-15')
        ->assertExitCode(0);

    $path = Credentials::path();

    expect($path)->toStartWith($this->configHome)
        ->and(is_file($path))->toBeTrue();

    $data = json_decode((string) file_get_contents($path), true);

    expect($data)->toMatchArray([
        'company' => 'acme',
        'token' => 'tok_secret_123',
        'version' => '2024-06-15',
    ]);

    expect(substr(sprintf('%o', fileperms($path)), -4))->toBe('0600');
});

it('shows the masked token with --show', function () {
    Credentials::writableStore()->put([
        'company' => 'acme',
        'token' => 'tok_secret_123',
        'version' => '2024-06-15',
    ]);

    $this->artisan('configure --show')
        ->assertExitCode(0)
        ->expectsOutputToContain('acme')
        ->expectsOutputToContain('****_123');
});
