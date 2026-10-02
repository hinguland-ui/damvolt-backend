<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Serves the admin panel's own CSS / JS. The files live outside /public, so a web server can never hand
 * them out directly; this route only answers when a page is loading them as a stylesheet or script.
 * Typing the URL in the address bar (or curl-ing it) gets a plain 404.
 */
class AssetController extends Controller
{
    public function show(Request $request, string $dir, string $file)
    {
        abort_unless(in_array($dir, ['css', 'js'], true) && preg_match('/^[A-Za-z0-9._-]+\.(css|js)$/', $file), 404);

        $path = resource_path("admin-assets/{$dir}/{$file}");
        abort_unless(is_file($path), 404);

        $dest = $request->headers->get('Sec-Fetch-Dest');
        if ($dest !== null) {
            abort_unless(in_array($dest, ['style', 'script'], true), 404);      // a page is using it — not "open in new tab"
        } else {
            // Browsers without Fetch-Metadata: only when the request comes from one of our own pages.
            $referrerHost = parse_url((string) $request->headers->get('Referer'), PHP_URL_HOST);
            abort_unless($referrerHost && $referrerHost === $request->getHost(), 404);
        }

        return response()->file($path, [
            'Content-Type' => $dir === 'css' ? 'text/css; charset=utf-8' : 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',          // URLs carry ?v=<file time>
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
