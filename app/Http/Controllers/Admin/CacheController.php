<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Activity;
use App\Support\Content;
use Illuminate\Support\Facades\Artisan;

class CacheController extends Controller
{
    /**
     * One click: clears every cache.
     *  - backend: application cache, config, routes, views, compiled files
     *  - website: the cached /api/content payload is dropped and its version number is bumped, so every
     *    visitor's browser throws away its stored copy and loads fresh content on the next page view.
     */
    public function clear()
    {
        Activity::log('cache_clear', 'Cleared all caches (admin + website)');
        Setting::put('system', ['content_version' => time()]);
        Content::flush();

        $failed = [];
        foreach (['cache:clear', 'config:clear', 'route:clear', 'view:clear', 'event:clear', 'clear-compiled'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable $e) {
                $failed[] = $command;
            }
        }

        return response()->json([
            'ok' => ! $failed,
            'message' => $failed ? 'Cleared, except: '.implode(', ', $failed) : 'All caches cleared (admin + website).',
        ]);
    }
}
