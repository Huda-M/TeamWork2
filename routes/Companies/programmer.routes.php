<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Company\Programmer\ProgrammerController;

Route::controller(ProgrammerController::class)->prefix('programmer')->middleware('auth:sanctum')->group(function () {
    Route::get('/list', 'index');
    Route::get('/{id}', 'show');
});