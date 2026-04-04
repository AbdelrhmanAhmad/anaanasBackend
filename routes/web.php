<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/import/', [\App\Http\Controllers\ImportOldController::class, 'index']);
Route::get('/syncData/', [\App\Http\Controllers\ImportOldController::class, 'syncData']);
