<?php

namespace App\Console\Commands;

use App\Support\Activity;
use Illuminate\Console\Command;

class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune';

    protected $description = 'Delete admin activity-log entries older than 7 days';

    public function handle(): int
    {
        $deleted = Activity::prune();
        $this->info("🧹 Removed {$deleted} activity-log entr".($deleted === 1 ? 'y' : 'ies').' older than '.Activity::KEEP_DAYS.' days.');

        return self::SUCCESS;
    }
}
