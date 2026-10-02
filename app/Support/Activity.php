<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Str;

/** Admin activity log. Never throws: logging must not be able to break the action being logged. */
class Activity
{
    public const KEEP_DAYS = 7;

    public static function log(string $action, string $description, ?User $user = null, ?string $actor = null): void
    {
        try {
            $user ??= auth()->user();
            $request = request();

            ActivityLog::create([
                'user_id' => $user?->id,
                'actor' => $actor ?? $user?->email,
                'action' => $action,
                'description' => Str::limit(trim(preg_replace('/\s+/', ' ', $description)), 250, '…'),
                'ip' => $request?->ip(),
                'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
                'created_at' => now(),
            ]);

            if (random_int(1, 50) === 1) {      // housekeeping even if the daily scheduler is not running
                self::prune();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function prune(): int
    {
        return ActivityLog::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
    }
}
