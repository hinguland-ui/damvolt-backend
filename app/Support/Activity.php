<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Str;

/** Admin activity log. Never throws: logging must not be able to break the action being logged. */
class Activity
{
    /** How many entries are kept when the admin has not chosen a number (Site Settings → Security). */
    public const DEFAULT_MAX = 1000;

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

    /** The "maximum logs" number saved in Site Settings → Security. */
    public static function maxLogs(): int
    {
        $max = (int) (Setting::section('security')['max_logs'] ?? 0);

        return $max >= 50 ? min($max, 10000) : self::DEFAULT_MAX;
    }

    /** Keeps only the newest maxLogs() entries; everything older is deleted. Returns how many were removed. */
    public static function prune(): int
    {
        $oldestKept = ActivityLog::query()->orderByDesc('id')->skip(self::maxLogs() - 1)->take(1)->value('id');

        return $oldestKept ? ActivityLog::where('id', '<', $oldestKept)->delete() : 0;
    }
}
