<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class Media
{
    /** Public URL of a stored path (absolute URLs pass through). Built from APP_URL, so one env var moves every image. */
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return preg_match('#^https?://#', $path) ? $path : rtrim(config('app.url'), '/').'/storage/'.ltrim($path, '/');
    }

    public static function store(UploadedFile $file, string $dir = 'uploads'): string
    {
        return $file->store($dir, 'public');
    }

    public static function delete(?string $path): void
    {
        // Only remove files uploaded through the panel, never the seeded defaults.
        if ($path && str_starts_with($path, 'uploads/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
