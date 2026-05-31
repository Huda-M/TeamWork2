<?php

use App\Http\Controllers\TeamChatController;

Route::controller(TeamChatController::class)->prefix('teams')->middleware('auth:sanctum')->group(function () {
    Route::get('/{team}/chat/messages', 'messages');
    Route::post('/{team}/chat/messages', 'send');
});