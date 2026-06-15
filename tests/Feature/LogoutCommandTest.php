<?php

use App\Support\CredentialStore;

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

it('forgets the active company and repoints active to what remains', function () {
    $store = app(CredentialStore::class);
    $store->setContext('acme', 'tok-a', '2024-06-15');
    $store->setContext('globex', 'tok-g', '2024-06-15');

    $this->artisan('logout')
        ->expectsChoice('Which company do you want to log out of?', 'globex', ['acme', 'globex'])
        ->expectsOutputToContain('Logged out of globex')
        ->assertExitCode(0);

    expect($store->companies())->toBe(['acme'])
        ->and($store->activeCompany())->toBe('acme');
});

it('can forget a named company', function () {
    $store = app(CredentialStore::class);
    $store->setContext('acme', 'tok-a', '2024-06-15');
    $store->setContext('globex', 'tok-g', '2024-06-15');

    $this->artisan('logout acme')->assertExitCode(0);

    expect($store->companies())->toBe(['globex'])
        ->and($store->activeCompany())->toBe('globex');
});

it('is a no-op when nothing is stored', function () {
    $this->artisan('logout')
        ->expectsOutputToContain('No credentials are stored')
        ->assertExitCode(0);
});
