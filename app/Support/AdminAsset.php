<?php

namespace App\Support;

class AdminAsset
{
    /** AdminAsset::url('css/admin.css') -> /admin/assets/css/admin.css?v=<file time> */
    public static function url(string $path): string
    {
        [$dir, $file] = explode('/', $path, 2);
        $full = resource_path("admin-assets/{$path}");

        return route('admin.asset', ['dir' => $dir, 'file' => $file]).'?v='.(is_file($full) ? filemtime($full) : 0);
    }
}
