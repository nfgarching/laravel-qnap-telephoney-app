<?php

use Illuminate\Support\Facades\Route;

// Startseite → Anrufliste (bzw. Login)
Route::redirect('/', '/telefon');

Route::middleware('auth')->group(function () {
    Route::view('/konto', 'auth.account')->name('account');
});
