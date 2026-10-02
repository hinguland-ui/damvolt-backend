<?php

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// The backend has no public pages: the root goes to the admin panel (guests land on the login form).
Route::redirect('/', '/admin');

// For search engines: all public pages of the website, generated from the database.
Route::get('/sitemap.xml', SitemapController::class);
