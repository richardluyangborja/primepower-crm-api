<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SPA fallback: serves the frontend build's index.html for direct visits
// (e.g. shared /survey/{token} links) on single-artifact deployments where
// the backend serves the frontend. Only active when public/index.html exists;
// on split deployments the frontend static host must provide its own rewrite.
Route::fallback(function () {
    $index = public_path('index.html');

    abort_unless(file_exists($index), 404);

    return response()->file($index);
});
