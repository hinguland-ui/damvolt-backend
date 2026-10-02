<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the public API so only the website itself can use it.
 *  - Writing (contact form): the request must carry an Origin/Referer that is listed in FRONTEND_URL.
 *  - Reading (content feed): the same, or a same-origin fetch from our own page. Typing the URL in the browser
 *    (a "navigate" request) or calling it with curl gets an EMPTY 404 — nothing to read.
 * The answers have no body at all, so they reveal nothing about the API.
 */
class VerifyFrontendOrigin
{
    public static function allowed(): array
    {
        return array_values(array_filter(array_map(
            fn ($o) => rtrim(trim($o), '/'),
            config('cors.allowed_origins', [])
        )));
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if (! $origin && ($referer = $request->headers->get('Referer'))) {
            $p = parse_url($referer);
            $origin = ($p['scheme'] ?? '').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '');
        }

        $fromWebsite = $origin && in_array(rtrim($origin, '/'), self::allowed(), true);

        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            // A browser fetch from a page on this same host sends Sec-Fetch-Site: same-origin (never "navigate").
            $sameOriginFetch = $request->headers->get('Sec-Fetch-Site') === 'same-origin'
                && $request->headers->get('Sec-Fetch-Mode') !== 'navigate';

            if (! $fromWebsite && ! $sameOriginFetch) {
                return response('', 404);
            }
        } elseif (! $fromWebsite) {
            return response('', 403);
        }

        return $next($request);
    }
}
