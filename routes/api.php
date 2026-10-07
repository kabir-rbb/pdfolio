<?php

use App\Http\Controllers\ToolController;
use App\Support\ToolRegistry;
use Illuminate\Support\Facades\Route;

Route::get('/tools', function () {
    return response()->json(ToolRegistry::all());
});

Route::post('/tools/{tool}', [ToolController::class, 'handle'])
    ->where('tool', '[a-z-]+');
