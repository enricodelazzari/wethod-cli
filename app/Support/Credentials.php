<?php

namespace App\Support;

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
     * Read an environment variable, treating unset and empty as absent.
     */
    public static function env(string $name): ?string
    {
        $value = getenv($name);

        return $value !== false && $value !== '' ? $value : null;
    }
}
