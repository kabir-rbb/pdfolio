<?php

use Illuminate\Support\Facades\Route;

// SPA fallback — everything else renders the Vue app
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
