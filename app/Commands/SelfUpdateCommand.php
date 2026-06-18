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

        $expectedHash = $this->fetchExpectedChecksum($release['assets'], $assetName);

        $currentPath = $this->getCurrentBinaryPath();
        $tmpPath = $currentPath.'.download';

        $downloaded = spin(
            callback: fn (): bool => $this->downloadTo($downloadUrl, $tmpPath),
            message: 'Downloading...'
        );

        if (! $downloaded) {
            @unlink($tmpPath);
            error('Failed to download the update.');

            return self::FAILURE;
        }

        // Verify integrity before touching the installed binary so a corrupt or
        // truncated download can never replace a working install.
        if ($expectedHash !== null && ! hash_equals($expectedHash, (string) hash_file('sha256', $tmpPath))) {
            @unlink($tmpPath);
            error('Checksum verification failed; the download is corrupted. Update aborted.');

            return self::FAILURE;
        }

        if (! $this->looksLikeExecutable($tmpPath, $platform)) {
            @unlink($tmpPath);
            error('The downloaded file is not a valid executable. Update aborted.');

            return self::FAILURE;
        }

        if ($expectedHash === null) {
            note('No published checksum found for this release; skipped checksum verification.');
        }

        $backupPath = $currentPath.'.old';

        if (file_exists($backupPath)) {
            unlink($backupPath);
        }

        // Keep the running binary in place by copying (not moving) it to the
        // backup, then swap the verified download in with an atomic rename.
        if (! @copy($currentPath, $backupPath)) {
            @unlink($tmpPath);
            error('Could not create a backup of the current binary. Update aborted.');

            return self::FAILURE;
        }

        chmod($tmpPath, 0755);

        if (! @rename($tmpPath, $currentPath)) {
            @unlink($tmpPath);
            error('Could not install the update. Your existing binary is unchanged.');

            return self::FAILURE;
        }

        outro("Successfully updated to {$version}!");
        note('Run `wethod self-update --rollback` to revert if needed.');

        return self::SUCCESS;
    }

    /**
     * Stream the asset straight to disk, returning false on any HTTP or
     * transport error so the caller never writes a bad response to the binary.
     */
    private function downloadTo(string $url, string $path): bool
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'wethod-cli'])
                ->timeout(300)
                ->sink($path)
                ->get($url);
        } catch (\Throwable) {
            return false;
        }

        if ($response->failed()) {
            return false;
        }

        return file_exists($path) && filesize($path) > 0;
    }

    /**
     * Fetch the expected SHA-256 for an asset from its companion `.sha256`
     * release file. Returns null when no checksum is published.
     *
     * @param  array<array{name: string, browser_download_url: string}>  $assets
     */
    private function fetchExpectedChecksum(array $assets, string $assetName): ?string
    {
        $url = $this->findAssetUrl($assets, $assetName.'.sha256');

        if ($url === null) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'wethod-cli'])->timeout(30)->get($url);
        } catch (\Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        // Accept either a bare hex digest or `sha256sum` format (`<hash>  <file>`).
        $token = strtok(trim($response->body()), " \t\n");

        if ($token === false || strlen($token) !== 64 || ! ctype_xdigit($token)) {
            return null;
        }

        return strtolower($token);
    }

    /**
     * Sanity-check the magic bytes so a non-executable payload (an HTML error
     * page, JSON, a half-written file) is never installed.
     */
    private function looksLikeExecutable(string $path, string $platform): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = fread($handle, 4);
        fclose($handle);

        if ($magic === false || strlen($magic) < 4) {
            return false;
        }

        /** @var array<int, int> $bytes */
        $bytes = array_values((array) unpack('C4', $magic));

        if (str_starts_with($platform, 'windows')) {
            return $bytes[0] === 0x4D && $bytes[1] === 0x5A; // "MZ"
        }

        if (str_starts_with($platform, 'darwin')) {
            // Mach-O thin (feedface/feedfacf) or universal (cafebabe), either endianness.
            $machoMagics = [
                [0xFE, 0xED, 0xFA, 0xCE], [0xCE, 0xFA, 0xED, 0xFE],
                [0xFE, 0xED, 0xFA, 0xCF], [0xCF, 0xFA, 0xED, 0xFE],
                [0xCA, 0xFE, 0xBA, 0xBE], [0xBE, 0xBA, 0xFE, 0xCA],
            ];

            return in_array($bytes, $machoMagics, true);
        }

        return $bytes === [0x7F, 0x45, 0x4C, 0x46]; // ELF "\x7fELF"
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
        $override = getenv('WETHOD_BINARY_PATH');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        if (\Phar::running(false)) {
            return \Phar::running(false);
        }

        return realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
    }
}
