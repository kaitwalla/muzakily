<?php

use Illuminate\Support\Facades\Route;

// Health check endpoint for load balancers and container orchestration
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/{any?}', fn () => view('app'))->where('any', '^(?!api\/).*$');
