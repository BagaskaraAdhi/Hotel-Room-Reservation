<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MidtransController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // user access
    Route::get('/payments', [MidtransController::class, 'index']);
    Route::get('/payments/{id}', [MidtransController::class, 'show']);
    Route::post('/payments', [MidtransController::class, 'store']);

    // admin access
    Route::middleware('role:admin')->group(function () {
        Route::put('/payments/{id}', [MidtransController::class, 'update']);
    });
}); 

Route::delete('/payments/{id}', [MidtransController::class, 'destroy']);
Route::post('/midtrans/callback', [MidtransController::class, 'callback']);