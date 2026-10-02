<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;

/**
 * Emergency switch: if reCAPTCHA is misconfigured (wrong keys / domain not allowed) the admin login
 * cannot be completed. Run `php artisan admin:captcha-off` on the server to turn it off again.
 */
class DisableCaptcha extends Command
{
    protected $signature = 'admin:captcha-off';

    protected $description = 'Turn reCAPTCHA off (website contact form and admin login) so you can sign in again';

    public function handle(): int
    {
        $settings = Setting::section('recaptcha');
        $settings['enabled'] = false;
        Setting::put('recaptcha', $settings);

        $this->info('reCAPTCHA is now OFF. Sign in, fix the keys in Site Settings → reCAPTCHA, then enable it again.');

        return self::SUCCESS;
    }
}
