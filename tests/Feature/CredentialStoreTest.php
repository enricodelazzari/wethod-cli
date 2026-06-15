<?php

use App\Support\Credentials;
use App\Support\CredentialStore;

beforeEach(function () {
    $this->configHome = sys_get_temp_dir().'/wethod-test-'.uniqid();
    putenv('XDG_CONFIG_HOME='.$this->configHome);
});

afterEach(function () {
    putenv('XDG_CONFIG_HOME');
    putenv('WETHOD_COMPANY');

    if (is_dir($this->configHome)) {
        @unlink($this->configHome.'/wethod/credentials.json');
        @rmdir($this->configHome.'/wethod');
        @rmdir($this->configHome);
    }
});

it('keeps a separate context per company and tracks the active one', function () {
    $store = new CredentialStore;

    $store->setContext('acme', 'tok-a', '2024-06-15');
    $store->setContext('globex', 'tok-g', '2025-01-01');

    expect($store->activeCompany())->toBe('globex')
        ->and($store->context('acme'))->toBe(['token' => 'tok-a', 'version' => '2024-06-15'])
        ->and($store->context())->toBe(['token' => 'tok-g', 'version' => '2025-01-01'])
        ->and($store->companies())->toBe(['acme', 'globex']);
});

it('lets WETHOD_COMPANY override the active company', function () {
    $store = new CredentialStore;
    $store->setContext('acme', 'tok-a', '2024-06-15');
    $store->setContext('globex', 'tok-g', '2024-06-15');

    putenv('WETHOD_COMPANY=acme');

    expect($store->activeCompany())->toBe('acme');
});

it('migrates the legacy flat credentials format on first read', function () {
    $dir = $this->configHome.'/wethod';
    mkdir($dir, 0700, true);
    file_put_contents(Credentials::path(), json_encode([
        'company' => 'acme',
        'token' => 'legacy-tok',
        'version' => '2024-06-15',
    ]));

    $store = new CredentialStore;

    expect($store->activeCompany())->toBe('acme')
        ->and($store->context('acme'))->toBe(['token' => 'legacy-tok', 'version' => '2024-06-15']);

    $data = json_decode((string) file_get_contents(Credentials::path()), true);

    expect($data)->not->toHaveKey('company')
        ->and($data)->toHaveKey('contexts');
});
