<?php

use App\Http\Controllers\NotificationController;

// Option A: Direct definition
Route::post('/v1/notifications/bulk', [NotificationController::class, 'bulk']);

// Option B: Using a prefix group (Recommended)
Route::prefix('v1')->group(function () {
    Route::post('notifications/bulk', [NotificationController::class, 'bulk']);
});