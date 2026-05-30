<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TeamMatchingController;

Route::controller(TeamMatchingController::class)->prefix('ai')->group(function () {
    Route::post('/match-teams', 'matchTeams')->middleware('auth:sanctum');
});
