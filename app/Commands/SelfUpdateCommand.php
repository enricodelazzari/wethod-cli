<?php

namespace App\Commands;

use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;

class SelfUpdateCommand extends Command
{
    protected $signature = 'self-update
        {--check : Only check for updates without installing}
        {--rollback : Rollback to the previous version}
        {--force : Force reinstall even if already on latest version}';

    protected $description = 'Update wethod to the latest version';

    private const GITHUB_REPO = 'enricodelazzari/wethod-cli';

    public function handle(): int
    {
        if ($this->option('rollback')) {
            return $this->rollback();
        }

        $currentVersion = ltrim((string) config('app.version'), 'v');
        $release = $this->getLatestRelease();

        if ($release === null) {
            error('Failed to fetch release information from GitHub.');

            return self::FAILURE;
        }

        $latestVersion = ltrim($release['tag_name'], 'v');

        if ($this->option('check')) {
            return $this->showVersionInfo($currentVersion, $latestVersion);
        }

        if (version_compare($currentVersion, $latestVersion, '>=') && ! $this->option('force')) {
            info("You're already on the latest version ({$currentVersion}).");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! confirm("Update from {$currentVersion} to {$latestVersion}?")) {
            return self::SUCCESS;
        }

        return $this->performUpdate($release, $latestVersion);
    }

    private function rollback(): int
    {
        $currentPath = $this->getCurrentBinaryPath();
        $backupPath = $currentPath.'.old';

        if (! file_exists($backupPath)) {
            error('No backup found. Cannot rollback.');

            return self::FAILURE;
        }

        rename($currentPath, $currentPath.'.new');
        rename($backupPath, $currentPath);
        unlink($currentPath.'.new');

        outro('Successfully rolled back to previous version.');

        return self::SUCCESS;
    }

    private function showVersionInfo(string $current, string $latest): int
    {
        $this->line("Current version: {$current}");
        $this->line("Latest version:  {$latest}");
        $this->newLine();

        if (version_compare($current, $latest, '<')) {
            info('Update available! Run `wethod self-update` to install.');
        } else {
            info("You're on the latest version.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{tag_name: string, assets: array<array{name: string, browser_download_url: string}>}  $release
     */
    private function performUpdate(array $release, string $version): int
    {
        $platform = $this->detectPlatform();
        $tag = $release['tag_name'];
        $assetName = "wethod-{$tag}-{$platform}";

        if ($platform === 'windows-x64') {
            $assetName .= '.exe';
        }

        $downloadUrl = $this->findAssetUrl($release['assets'], $assetName);

        if ($downloadUrl === null) {
            error("Could not find binary for platform: {$platform}");

            return self::FAILURE;
        }

        $binary = spin(
            callback: fn (): string => Http::withOptions(['sink' => null])->get($downloadUrl)->body(),
            message: 'Downloading...'
        );

        if (empty($binary)) {
            error('Failed to download the update.');

            return self::FAILURE;
        }

        $currentPath = $this->getCurrentBinaryPath();
        $backupPath = $currentPath.'.old';

        if (file_exists($backupPath)) {
            unlink($backupPath);
        }

        rename($currentPath, $backupPath);

        file_put_contents($currentPath, $binary);
        chmod($currentPath, 0755);

        outro("Successfully updated to {$version}!");
        note('Run `wethod self-update --rollback` to revert if needed.');

        return self::SUCCESS;
    }

    private function detectPlatform(): string
    {
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');

        return match (true) {
            $os === 'Darwin' && $arch === 'arm64' => 'darwin-arm64',
            $os === 'Darwin' => 'darwin-x64',
            $os === 'Linux' && in_array($arch, ['arm64', 'aarch64']) => 'linux-arm64',
            $os === 'Linux' => 'linux-x64',
            $os === 'Windows' => 'windows-x64',
            default => 'linux-x64',
        };
    }

    /**
     * @return array{tag_name: string, assets: array<array{name: string, browser_download_url: string}>}|null
     */
    private function getLatestRelease(): ?array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'wethod-cli',
            'Accept' => 'application/vnd.github+json',
        ])->get('https://api.github.com/repos/'.self::GITHUB_REPO.'/releases/latest');

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    /**
     * @param  array<array{name: string, browser_download_url: string}>  $assets
     */
    private function findAssetUrl(array $assets, string $name): ?string
    {
        foreach ($assets as $asset) {
            if ($asset['name'] === $name) {
                return $asset['browser_download_url'];
            }
        }

        return null;
    }

    private function getCurrentBinaryPath(): string
    {
        if (\Phar::running(false)) {
            return \Phar::running(false);
        }

        return realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
    }
}
