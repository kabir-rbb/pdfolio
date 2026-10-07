<?php

use App\Http\Controllers\ToolController;
use App\Support\ToolRegistry;
use Illuminate\Support\Facades\Route;

Route::get('/api/tools', function () {
    return response()->json(ToolRegistry::all());
});

Route::post('/api/tools/{tool}', [ToolController::class, 'handle'])
    ->where('tool', '[a-z-]+');

// SPA fallback — everything else renders the Vue app
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api).*$');
