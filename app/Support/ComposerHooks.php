<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;

/**
 * Composer hooks that run entirely inside PHP.
 *
 * Laravel's default `@php artisan package:discover` starts a child process, which needs proc_open — a function many
 * shared hosts switch off ("The Process class relies on proc_open, which is not available"). Building the package
 * manifest directly gives the same result without spawning anything.
 */
class ComposerHooks
{
    public static function discoverPackages(): void
    {
        $base = dirname(__DIR__, 2);

        try {
            (new PackageManifest(new Filesystem, $base, $base.'/bootstrap/cache/packages.php'))->build();
            echo "Discovered Packages\n";
        } catch (\Throwable $e) {
            // Never fail an install because of this: Laravel rebuilds the manifest on first use anyway.
            echo 'Package discovery skipped: '.$e->getMessage()."\n";
        }
    }
}
