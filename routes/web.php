<?php

use App\Http\Controllers\PublicFileController;
use App\Http\Controllers\SitemapController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The backend has no public pages: the root goes to the admin panel (guests land on the login form).
Route::redirect('/', '/admin');

// For search engines: all public pages of the website, generated from the database.
Route::get('/sitemap.xml', SitemapController::class);

// Uploaded pictures. Apache/nginx serve them directly when the `public/storage` link exists; otherwise (link missing,
// symlinks not allowed) this route delivers them, so a logo never turns into a 404. No session/cookies for images.
Route::get('/storage/{path}', PublicFileController::class)
    ->where('path', '.*')
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, AddQueuedCookiesToResponse::class, PreventRequestForgery::class])
    ->name('public-file');
