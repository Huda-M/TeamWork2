<?php

use App\Http\Controllers\Company\Auth\AuthController;
use App\Http\Controllers\Company\Auth\ProfileController;
use Illuminate\Support\Facades\Route;

Route::controller(AuthController::class)->prefix('company/auth')->group(function () {
    Route::post('/login', 'login');
    Route::post('/register', 'register');
    Route::get('/{provider}/redirect', 'redirectToProvider');
    Route::get('/{provider}/callback', 'handleProviderCallback');
});

Route::controller(AuthController::class)->prefix('company/auth')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', 'logout');
    Route::post('/change-password', 'changePassword');
});

Route::controller(ProfileController::class)->prefix('company/profile')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', 'me');
    Route::post('/complete', 'completeProfile');
    Route::post('/update', 'updateProfile');
    Route::delete('/delete', 'deleteProfile');
});