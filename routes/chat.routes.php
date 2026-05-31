<?php

use App\Http\Controllers\TeamChatController;
use Illuminate\Support\Facades\Route;

Route::controller(TeamChatController::class)->prefix('teams')->middleware('auth:sanctum')->group(function () {
    Route::get('/{team}/chat/messages', 'messages');
    Route::post('/{team}/chat/messages', 'send');
});