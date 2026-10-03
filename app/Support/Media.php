<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Media
{
    /** Folders of the public disk that belong to the admin panel's pictures (uploads + the starter pictures). */
    private const FOLDERS = ['uploads/', 'images/', 'brand/'];

    /** Database columns that hold a picture path: [table, column]. Pictures inside Site/Home Settings are found through the settings table. */
    private const COLUMNS = [['hero_slides', 'image'], ['services', 'image'], ['industries', 'image']];

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

    /**
     * Removes a picture file when it is replaced or removed, so old pictures never pile up on the server.
     * It is called while the record still points at the file, so the file is kept only when ANOTHER place
     * (a second slide, a setting …) uses the very same path.
     */
    public static function delete(?string $path): void
    {
        if (! self::isManaged($path) || self::references($path) > 1) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /** True for a path inside the picture folders (never an outside URL or a path that climbs out with ..). */
    private static function isManaged(?string $path): bool
    {
        if (! $path || str_contains($path, '..') || preg_match('#^([a-z]+:)?//#i', $path)) {
            return false;
        }

        foreach (self::FOLDERS as $folder) {
            if (str_starts_with($path, $folder)) {
                return true;
            }
        }

        return false;
    }

    /** How many places in the database point at this picture. */
    public static function references(string $path): int
    {
        $count = 0;

        foreach (self::COLUMNS as [$table, $column]) {
            $count += DB::table($table)->where($column, $path)->count();
        }

        $needle = '"'.$path.'"';
        foreach (Setting::query()->pluck('value') as $json) {
            $count += substr_count((string) $json, $needle);
        }

        return $count;
    }

    /**
     * Deletes pictures in uploads/ that nothing uses any more (left over from older versions).
     * Files younger than a day are skipped, so a picture that is being uploaded right now is never touched.
     *
     * @return int number of files removed
     */
    public static function cleanOrphans(): int
    {
        $disk = Storage::disk('public');
        $removed = 0;

        foreach ($disk->allFiles('uploads') as $file) {
            if (now()->timestamp - $disk->lastModified($file) < 86400) {
                continue;
            }
            if (self::references($file) === 0) {
                $disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }
}
