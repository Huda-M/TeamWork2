<?php 
 
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

Route::controller(NotificationController::class)->middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', 'index');
    Route::get('/unread-count', 'unreadCount');
    Route::post('/{id}/read', 'markAsRead');
    Route::post('/read-all', 'markAllAsRead');
});