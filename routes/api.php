<?php

use App\Http\Controllers\Api\ContactController;
use App\Support\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// One cached payload with everything the React site renders (site settings, home sections,
// services, industries, reviews, FAQs, legal pages). The ETag lets the browser skip the
// download when nothing has changed.
Route::get('content', function (Request $request) {
    $payload = Content::get();

    $response = response()->json($payload, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $response->setEtag(md5($response->getContent()));
    // Always revalidate: the browser asks "has it changed?" and gets a tiny 304 if not,
    // so a refresh right after saving in the admin panel shows the new content.
    $response->headers->set('Cache-Control', 'no-cache, must-revalidate');
    $response->isNotModified($request); // turns this into a 304 when the ETag matches

    return $response;
})->middleware(['frontend', 'throttle:content']);

// Contact form: only from the website origin(s) in FRONTEND_URL, rate-limited, honeypot-protected.
Route::post('contact', [ContactController::class, 'store'])->middleware(['frontend', 'throttle:contact']);
