<?php

use App\Http\Controllers\NotificationController;

Route::prefix('v1')->group(function () {
    Route::post('notifications/bulk', [NotificationController::class, 'sendBulk']);
    Route::get('notifications/{recipient_id}/history', [NotificationController::class, 'getHistory']);
});