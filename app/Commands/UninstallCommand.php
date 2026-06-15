<?php

namespace App\Commands;

use App\Support\Credentials;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;

class UninstallCommand extends Command
{
    protected $signature = 'uninstall
        {--keep-data : Keep stored credentials and the cached spec}
        {--force : Skip confirmation prompts}';

    protected $description = 'Remove the wethod binary and (optionally) its stored data';

    public function handle(): int
    {
        $binary = $this->getCurrentBinaryPath();

        if ($this->isSourceCheckout($binary)) {
            error('This looks like a source checkout, not an installed binary.');
            note('Delete the cloned repository instead, or remove the global symlink you created.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! confirm("Remove wethod from {$binary}?", default: false)) {
            info('Cancelled.');

            return self::SUCCESS;
        }

        if (! $this->option('keep-data')) {
            $this->removeData();
        }

        if (! $this->removeBinary($binary)) {
            return self::FAILURE;
        }

        outro('wethod has been uninstalled.');

        return self::SUCCESS;
    }

    /**
     * Remove stored credentials and the cached spec.
     */
    private function removeData(): void
    {
        $dir = Credentials::configDir();

        if (! is_dir($dir)) {
            return;
        }

        $remove = $this->option('force') || confirm(
            "Also remove stored credentials and cache in {$dir}?",
            default: true,
        );

        if ($remove) {
            $this->deleteDirectory($dir);
            info('Removed stored data.');
        }
    }

    /**
     * Remove the binary and any backup left by `self-update`.
     */
    private function removeBinary(string $binary): bool
    {
        $backup = $binary.'.old';

        if (file_exists($backup)) {
            @unlink($backup);
        }

        if (! @unlink($binary)) {
            error("Could not remove {$binary}. You may need to delete it manually (e.g. with sudo).");

            return false;
        }

        return true;
    }

    /**
     * Recursively delete a directory.
     */
    private function deleteDirectory(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;

            is_dir($path) ? $this->deleteDirectory($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    /**
     * Bail out when running from a Composer checkout, so we never delete the
     * project's own entry script instead of an installed binary.
     */
    private function isSourceCheckout(string $binary): bool
    {
        if (\Phar::running(false)) {
            return false;
        }

        return is_file(base_path('composer.json'))
            && is_dir(base_path('vendor'))
            && str_starts_with($binary, base_path());
    }

    private function getCurrentBinaryPath(): string
    {
        if (\Phar::running(false)) {
            return \Phar::running(false);
        }

        return realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
    }
}
