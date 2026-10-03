<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the database tables that grow by themselves small, so the admin panel stays fast for months:
 *  - activity log: only the newest "maximum logs" entries
 *  - cache table: expired rows (Laravel's database cache never deletes them on its own)
 *  - sessions table: sessions that have run out
 *  - uploads folder: pictures that no record uses any more
 * Runs every night from the scheduler and — for hosts without cron — at most once every 6 hours while the panel is used.
 * Never throws.
 */
class Housekeeping
{
    public static function runIfDue(): void
    {
        try {
            if (Cache::add('housekeeping:due', 1, 6 * 3600)) {
                self::run();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** @return array{logs: int, cache: int, sessions: int, files: int} rows / files removed */
    public static function run(): array
    {
        $done = ['logs' => 0, 'cache' => 0, 'sessions' => 0, 'files' => 0];

        // Each job on its own, so one failing never stops the others.
        $jobs = [
            'logs' => fn () => Activity::prune(),
            'files' => fn () => Media::cleanOrphans(),      // uploaded pictures nothing uses any more
            'cache' => function () {
                if (config('cache.default') !== 'database') {
                    return 0;
                }
                $removed = DB::table(config('cache.stores.database.table') ?: 'cache')->where('expiration', '<', time())->delete();
                DB::table(config('cache.stores.database.lock_table') ?: 'cache_locks')->where('expiration', '<', time())->delete();

                return $removed;
            },
            'sessions' => function () {
                if (config('session.driver') !== 'database') {
                    return 0;
                }

                return DB::table(config('session.table') ?: 'sessions')
                    ->where('last_activity', '<', time() - ((int) config('session.lifetime', 120)) * 60)
                    ->delete();
            },
        ];
        foreach ($jobs as $name => $job) {
            try {
                $done[$name] = (int) $job();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $done;
    }
}
