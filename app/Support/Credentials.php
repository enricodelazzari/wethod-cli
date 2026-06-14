<?php

namespace App\Support;

class Credentials
{
    /**
     * Resolve the current user's home directory across platforms.
     */
    public static function homeDir(): string
    {
        $home = getenv('HOME') ?: getenv('USERPROFILE');

        return $home !== false && $home !== '' ? rtrim($home, '/\\') : sys_get_temp_dir();
    }

    /**
     * The directory where Wethod CLI keeps its state (XDG-aware).
     */
    public static function configDir(): string
    {
        $xdg = getenv('XDG_CONFIG_HOME');

        $base = $xdg !== false && $xdg !== '' ? rtrim($xdg, '/\\') : self::homeDir().'/.config';

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
     * Load the stored credentials, or an empty array if none exist.
     *
     * @return array<string, mixed>
     */
    public static function load(): array
    {
        $path = self::path();

        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Persist credentials to disk with owner-only permissions.
     *
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        $dir = self::configDir();

        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $path = self::path();

        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        @chmod($path, 0600);
    }
}
