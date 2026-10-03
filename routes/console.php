<?php

use App\Support\Housekeeping;
use Illuminate\Support\Facades\Schedule;

// Nightly tidy-up: activity log trimmed to its maximum, expired cache rows and sessions deleted.
// (Needs the usual server cron:  * * * * * php /path/to/backend/artisan schedule:run  — without cron the same
//  tidy-up runs by itself, at most every 6 hours, while the admin panel is in use.)
// A closure (not ->command()) so it runs inside PHP — hosts without proc_open cannot start child processes.
Schedule::call(fn () => Housekeeping::run())->name('housekeeping')->daily();
