<?php

use Illuminate\Support\Facades\Http;

/**
 * Magic bytes for a binary the host platform will accept as an executable,
 * mirroring SelfUpdateCommand::looksLikeExecutable().
 */
function validExecutableBody(): string
{
    $magic = match (PHP_OS_FAMILY) {
        'Darwin' => "\xCF\xFA\xED\xFE",
        'Windows' => 'MZ',
        default => "\x7F\x45\x4C\x46",
    };

    return $magic.str_repeat("\x00", 64);
}

/**
 * @return array{tag_name: string, assets: array<array{name: string, browser_download_url: string}>}
 */
function fakeRelease(string $tag, bool $withChecksums): array
{
    $platforms = ['darwin-arm64', 'darwin-x64', 'linux-arm64', 'linux-x64', 'windows-x64.exe'];
    $assets = [];

    foreach ($platforms as $platform) {
        $name = "wethod-{$tag}-{$platform}";
        $assets[] = ['name' => $name, 'browser_download_url' => "https://dl.test/{$name}"];

        if ($withChecksums) {
            $assets[] = ['name' => $name.'.sha256', 'browser_download_url' => "https://dl.test/{$name}.sha256"];
        }
    }

    return ['tag_name' => $tag, 'assets' => $assets];
}

beforeEach(function () {
    config(['app.version' => '0.1.0']);

    $this->binaryPath = tempnam(sys_get_temp_dir(), 'wethod-bin-');
    file_put_contents($this->binaryPath, 'OLD-BINARY');
    putenv('WETHOD_BINARY_PATH='.$this->binaryPath);
});

afterEach(function () {
    putenv('WETHOD_BINARY_PATH');

    foreach ([$this->binaryPath, $this->binaryPath.'.old', $this->binaryPath.'.download'] as $path) {
        if (is_string($path) && file_exists($path)) {
            unlink($path);
        }
    }
});

it('reports an available update with --check', function () {
    Http::fake([
        'api.github.com/*' => Http::response(fakeRelease('v0.2.0', true)),
    ]);

    $this->artisan('self-update', ['--check' => true])
        ->expectsOutputToContain('0.2.0')
        ->expectsOutputToContain('Update available')
        ->assertExitCode(0);
});

it('replaces the binary and keeps a backup when the checksum matches', function () {
    $binary = validExecutableBody();
    $hash = hash('sha256', $binary);

    Http::fake(function ($request) use ($binary, $hash) {
        $url = $request->url();

        if (str_contains($url, 'api.github.com')) {
            return Http::response(fakeRelease('v0.2.0', true));
        }

        if (str_ends_with($url, '.sha256')) {
            return Http::response("{$hash}  binary");
        }

        return Http::response($binary);
    });

    $this->artisan('self-update', ['--force' => true])
        ->expectsOutputToContain('Successfully updated')
        ->assertExitCode(0);

    expect(file_get_contents($this->binaryPath))->toBe($binary);
    expect(file_get_contents($this->binaryPath.'.old'))->toBe('OLD-BINARY');
    expect(file_exists($this->binaryPath.'.download'))->toBeFalse();
});

it('aborts and leaves the binary untouched when the checksum does not match', function () {
    $binary = validExecutableBody();

    Http::fake(function ($request) use ($binary) {
        $url = $request->url();

        if (str_contains($url, 'api.github.com')) {
            return Http::response(fakeRelease('v0.2.0', true));
        }

        if (str_ends_with($url, '.sha256')) {
            return Http::response(str_repeat('0', 64).'  binary');
        }

        return Http::response($binary);
    });

    $this->artisan('self-update', ['--force' => true])
        ->expectsOutputToContain('Checksum verification failed')
        ->assertExitCode(1);

    expect(file_get_contents($this->binaryPath))->toBe('OLD-BINARY');
    expect(file_exists($this->binaryPath.'.old'))->toBeFalse();
    expect(file_exists($this->binaryPath.'.download'))->toBeFalse();
});

it('aborts when the downloaded file is not a valid executable', function () {
    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, 'api.github.com')) {
            return Http::response(fakeRelease('v0.2.0', false));
        }

        return Http::response('<html>not a binary</html>');
    });

    $this->artisan('self-update', ['--force' => true])
        ->expectsOutputToContain('not a valid executable')
        ->assertExitCode(1);

    expect(file_get_contents($this->binaryPath))->toBe('OLD-BINARY');
    expect(file_exists($this->binaryPath.'.old'))->toBeFalse();
});
