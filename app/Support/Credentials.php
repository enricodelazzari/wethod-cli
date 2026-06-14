<?php

namespace App\Support;

use Spatie\Valuestore\Valuestore;

class Credentials
{
    /**
     * Resolve the current user's home directory across platforms.
     */
    public static function homeDir(): string
    {
        $home = self::env('HOME') ?? self::env('USERPROFILE');

        return $home !== null ? rtrim($home, '/\\') : sys_get_temp_dir();
    }

    /**
     * The directory where Wethod CLI keeps its state (XDG-aware).
     */
    public static function configDir(): string
    {
        $xdg = self::env('XDG_CONFIG_HOME');

        $base = $xdg !== null ? rtrim($xdg, '/\\') : self::homeDir().'/.config';

        return $base.'/wethod';
    }

    /**
     * Where the cached OpenAPI spec lives.
     */
    public static function cacheDir(): string
    {
        return self::configDir().'/cache';
    }

    /**
     * Path to the credentials file.
     */
    public static function path(): string
    {
        return self::configDir().'/credentials.json';
    }

    /**
     * A read-only credentials store. Reading never touches the filesystem,
     * so env-only users don't get an empty credentials file created.
     */
    public static function store(): Valuestore
    {
        return Valuestore::make(self::path());
    }

    /**
     * A credentials store whose file is pre-created with owner-only
     * permissions (dir 0700, file 0600). file_put_contents preserves the
     * permissions of an existing file, so subsequent writes stay 0600.
     */
    public static function writableStore(): Valuestore
    {
        $dir = self::configDir();

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $path = self::path();

        if (! file_exists($path)) {
            touch($path);
        }

        @chmod($path, 0600);

        return Valuestore::make($path);
    }

    /**
     * Read an environment variable, treating unset and empty as absent.
     */
    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return $value !== false && $value !== '' ? $value : null;
    }
}
