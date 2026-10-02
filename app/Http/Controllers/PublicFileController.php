<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Serves uploaded pictures at /storage/{path} straight from the "public" disk.
 *
 * Normally the web server delivers these files itself through the `public/storage` symlink (php artisan storage:link).
 * When that link is missing — or the host does not allow symlinks — requests fall through to here, so images never
 * break. Only picture files, only inside the public disk, never hidden files or anything outside it.
 */
class PublicFileController extends Controller
{
    private const MIME = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
    ];

    public function __invoke(Request $request, string $path)
    {
        $path = ltrim($path, '/');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        abort_unless(
            isset(self::MIME[$ext])
                && preg_match('#^[A-Za-z0-9._/-]+$#', $path)
                && ! str_contains($path, '..')
                && ! str_starts_with(basename($path), '.'),
            404
        );

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        // Belt and braces: the resolved file must really live inside the disk's root.
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        abort_unless($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file), 404);

        $response = response()->file($file, [
            'Content-Type' => self::MIME[$ext],
            'Cache-Control' => 'public, max-age=86400',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setLastModified(\DateTimeImmutable::createFromFormat('U', (string) filemtime($file)));
        $response->isNotModified($request);

        return $response;
    }
}
