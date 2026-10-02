<?php

namespace App\Providers;

use App\Models\ContactMessage;
use App\Models\LegalPage;
use App\Models\Setting;
use App\Support\HtmlSanitizer;
use App\Support\Media;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Validator::extend('safe_url', fn ($attribute, $value) => $value === null || $value === '' || HtmlSanitizer::safeUrl($value), 'The :attribute must be a normal web link (https://…), a /path, mailto: or tel:.');

        // Public content feed: one request per page view, so this only stops scripted hammering.
        RateLimiter::for('content', fn ($request) => Limit::perMinute(300)->by($request->ip()));

        // Contact form: 3 per minute and 30 per day per IP.
        RateLimiter::for('contact', fn ($request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(30)->by($request->ip()),
        ]);

        // Every admin view gets the dynamic brand (logo, favicon, site name) and the Legal Pages menu.
        View::composer('admin.*', function ($view) {
            static $shared = null;

            $shared ??= (function () {
                try {
                    $brand = Setting::section('brand');
                    $legal = LegalPage::orderBy('sort_order')->orderBy('id')->get(['id', 'title']);
                    $unread = ContactMessage::where('is_read', false)->count();
                } catch (\Throwable) {
                    $brand = [];
                    $legal = collect();
                    $unread = 0;
                }

                return [
                    'site' => [
                        'name' => $brand['site_name'] ?? config('app.name'),
                        'short' => $brand['short_name'] ?? config('app.name'),
                        'description' => $brand['description'] ?? '',
                        'logo' => Media::url($brand['footer_logo'] ?? ($brand['logo'] ?? null)), // sidebar is dark
                        'logoLight' => Media::url($brand['logo'] ?? null),                       // login page is light
                        'favicon' => Media::url($brand['favicon'] ?? null),
                    ],
                    'frontendUrl' => (config('cors.allowed_origins')[0] ?? config('app.url')),
                    'legalMenu' => $legal,
                    'unreadEnquiries' => $unread,
                ];
            })();

            $view->with($shared);
        });
    }
}
