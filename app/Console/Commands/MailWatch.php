<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Live view of what happens to every contact-form enquiry:
 *   php artisan mail:watch
 * Leave it running in a second terminal — each new enquiry prints 📨 ✅ ❌ lines as the e-mails go out.
 */
class MailWatch extends Command
{
    protected $signature = 'mail:watch {--all : also show older lines already in the log}';

    protected $description = 'Live console of contact-form e-mails (who was mailed, what failed)';

    private const MARKERS = ['📨', '📡', '✅', '❌', '⚠️', 'ℹ️', '➖', '🏁'];

    public function handle(): int
    {
        $file = storage_path('logs/laravel.log');
        if (! is_file($file)) {
            file_put_contents($file, '');
        }

        $this->components->info('👀 Watching enquiry e-mails… (Ctrl+C to stop)');

        $handle = fopen($file, 'r');
        if (! $this->option('all')) {
            fseek($handle, 0, SEEK_END);
        }

        while (true) {
            while (($line = fgets($handle)) !== false) {
                foreach (self::MARKERS as $marker) {
                    if (str_contains($line, $marker)) {
                        // "[2026-10-02 15:46:17] local.INFO: ✅ …"  ->  "15:46:17  ✅ …"
                        $this->line(preg_replace('/^\[\d{4}-\d{2}-\d{2} (\d{2}:\d{2}:\d{2})\] \w+\.\w+: /', '$1  ', rtrim($line)));
                        break;
                    }
                }
            }
            fseek($handle, 0, SEEK_CUR);                     // clears PHP's "reached EOF" flag so new lines are read
            clearstatcache(false, $file);
            if (filesize($file) < ftell($handle)) {          // log was rotated / cleared
                fseek($handle, 0);
            }
            sleep(1);
        }
    }
}
