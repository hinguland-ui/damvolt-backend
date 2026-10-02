<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CacheController;
use App\Http\Controllers\Admin\CrudController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\Admin\LegalPageController;
use App\Http\Controllers\Admin\RecaptchaController;
use App\Http\Controllers\Admin\SectionController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\SmtpController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::get('assets/{dir}/{file}', [AssetController::class, 'show'])->name('asset');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('users', UserController::class)->except('show');
    Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');

    Route::post('cache/clear', [CacheController::class, 'clear'])->middleware('throttle:10,1')->name('cache.clear');

    // Website enquiries (contact form) + SMTP test
    Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::post('enquiries/{enquiry}/read', [EnquiryController::class, 'read'])->name('enquiries.read');
    Route::post('enquiries/{enquiry}/resend', [EnquiryController::class, 'resend'])->middleware('throttle:10,1')->name('enquiries.resend');
    Route::delete('enquiries/{enquiry}', [EnquiryController::class, 'destroy'])->name('enquiries.destroy');
    Route::post('recaptcha/test', [RecaptchaController::class, 'test'])->middleware('throttle:20,1')->name('recaptcha.test');
    Route::post('smtp/test', [SmtpController::class, 'test'])->middleware('throttle:10,1')->name('smtp.test');

    // Tabbed pages: Home Page (/admin/home) and Site Settings (/admin/settings)
    Route::get('{group}', [SectionController::class, 'show'])->whereIn('group', ['home', 'settings', 'seo'])->name('sections.show');
    Route::put('{group}/{section}', [SectionController::class, 'update'])->whereIn('group', ['home', 'settings', 'seo'])->name('sections.update');

    // Services (+ drag & drop order)
    Route::post('services/reorder', [ServiceController::class, 'reorder'])->name('services.reorder');
    Route::resource('services', ServiceController::class)->except('show');

    // Legal pages
    Route::post('legal/reorder', [LegalPageController::class, 'reorder'])->name('legal.reorder');
    Route::resource('legal', LegalPageController::class)->except('show')->parameters(['legal' => 'legal']);

    // Modal list managers: slides, industries, testimonials, faqs, categories
    Route::prefix('manage/{resource}')->name('crud.')->group(function () {
        Route::get('/', [CrudController::class, 'index'])->name('index');
        Route::post('/', [CrudController::class, 'store'])->name('store');
        Route::post('reorder', [CrudController::class, 'reorder'])->name('reorder');
        Route::put('{id}', [CrudController::class, 'update'])->name('update');
        Route::delete('{id}', [CrudController::class, 'destroy'])->name('destroy');
    });

});
