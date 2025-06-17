<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MidtransController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\RiviewController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // all access
    Route::get('/room', [RoomController::class, 'index']);
    Route::get('/room/{id}', [RoomController::class, 'show']);

    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::get('/reservations/{id}', [ReservationController::class, 'show']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::put('/reservations/{id}', [ReservationController::class, 'update']);

    Route::get('/payments', [MidtransController::class, 'index']);
    Route::get('/payments/{id}', [MidtransController::class, 'show']);
    Route::post('/payments', [MidtransController::class, 'store']);

    Route::get('/reviews', [RiviewController::class, 'index']);
    Route::get('/reviews/{id}', [RiviewController::class, 'show']);
    Route::post('/reviews', [RiviewController::class, 'store']);
    Route::put('/reviews/{id}', [RiviewController::class, 'update']);
    Route::delete('/reviews/{id}', [RiviewController::class, 'destroy']);

    // admin access
    Route::middleware('role:admin')->group(function () {
        Route::post('/room', [RoomController::class, 'store']);
        Route::put('/room/{id}', [RoomController::class, 'update']);
        Route::delete('/room/{id}', [RoomController::class, 'destroy']);

        Route::put('/payments/{id}', [MidtransController::class, 'update']);
        Route::delete('/payments/{id}', [MidtransController::class, 'destroy']);

        Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
    });
});

Route::post('/midtrans/callback', [MidtransController::class, 'callback']);
