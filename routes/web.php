<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::view('/chat-test', 'chat-test');

require __DIR__.'/auth.php';
