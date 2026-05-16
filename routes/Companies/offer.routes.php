<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Company\JopOffer\JopOfferController;

Route::controller(JopOfferController::class)->prefix('job-offer')->middleware('auth:sanctum')->group(function () {
    Route::post('/send', 'store');
    Route::get('/', 'index');
});