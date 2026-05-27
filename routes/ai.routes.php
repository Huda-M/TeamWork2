<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TeamMatchingController;

Route::controller(TeamMatchingController::class)->prefix('ai')->group(function () {
    Route::post('/match-teams', 'joinTeam')->middleware('auth:sanctum');
    Route::post('/suggest-team', 'suggestTeam');
    Route::get('/suggested-teams', 'getSuggestedTeams')->middleware('auth:sanctum');
});
