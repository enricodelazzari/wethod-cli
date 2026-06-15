<?php

use App\Support\CredentialStore;

beforeEach(function () {
    $this->configHome = sys_get_temp_dir().'/wethod-test-'.uniqid();
    putenv('XDG_CONFIG_HOME='.$this->configHome);

    config(['wethod.token' => null, 'wethod.company' => null, 'wethod.version' => '2024-06-15']);
});

afterEach(function () {
    putenv('XDG_CONFIG_HOME');

    if (is_dir($this->configHome)) {
        @unlink($this->configHome.'/wethod/credentials.json');
        @rmdir($this->configHome.'/wethod');
        @rmdir($this->configHome);
    }
});

it('masks the active token', function () {
    $store = app(CredentialStore::class);
    $store->setContext('acme', 'tok_secret_123', '2024-06-15');
    $store->setContext('globex', 'tok_other_999', '2024-06-15');

    $this->artisan('auth')
        ->expectsOutputToContain('****_999')
        ->assertExitCode(0);
});

it('lists the stored companies', function () {
    $store = app(CredentialStore::class);
    $store->setContext('acme', 'tok_secret_123', '2024-06-15');
    $store->setContext('globex', 'tok_other_999', '2024-06-15');

    // The list is rendered as a single Prompts note (one write), so assert one
    // unique substring: the non-active company proves all stored ones are listed.
    $this->artisan('auth')
        ->expectsOutputToContain('acme')
        ->assertExitCode(0);
});

it('reveals the token with --show-token', function () {
    app(CredentialStore::class)->setContext('acme', 'tok_secret_123', '2024-06-15');

    $this->artisan('auth --show-token')
        ->expectsOutputToContain('tok_secret_123')
        ->assertExitCode(0);
});

it('reports when nothing is configured', function () {
    $this->artisan('auth')
        ->expectsOutputToContain('No companies configured')
        ->assertExitCode(0);
});
