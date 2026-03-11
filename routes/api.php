<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['resolve.tenant'])->group(function () {
    Route::post('/auth/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
        Route::get('/account/balance', [\App\Http\Controllers\Api\AccountController::class, 'balance']);
        Route::post('/transactions', [\App\Http\Controllers\Api\TransactionController::class, 'store']);
        Route::get('/transactions', [\App\Http\Controllers\Api\TransactionController::class, 'index']);
    });
});
