<?php

use App\Support\Activity;
use Illuminate\Support\Facades\Schedule;

// Admin activity log: keep 7 days, delete the rest every night.
// (Needs the usual server cron:  * * * * * php /path/to/backend/artisan schedule:run  — and the log also
//  tidies itself while the panel is in use, so it never grows without limit even without cron.)
// A closure (not ->command()) so it runs inside PHP — hosts without proc_open cannot start child processes.
Schedule::call(fn () => Activity::prune())->name('activity-prune')->daily();
