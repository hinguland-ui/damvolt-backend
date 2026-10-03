<?php

namespace App\Console\Commands;

use App\Support\Housekeeping;
use Illuminate\Console\Command;

class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune';

    protected $description = 'Trim the activity log to its maximum size and delete expired cache rows and sessions';

    public function handle(): int
    {
        $done = Housekeeping::run();
        $this->info("🧹 Removed {$done['logs']} old log entries, {$done['cache']} expired cache rows, {$done['sessions']} expired sessions, {$done['files']} unused pictures.");

        return self::SUCCESS;
    }
}
