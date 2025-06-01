<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MidtransController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/payments', [MidtransController::class, 'index']);
Route::post('/payments', [MidtransController::class, 'store']);
Route::get('/payments/{id}', [MidtransController::class, 'show']);
Route::put('/payments/{id}', [MidtransController::class, 'update']);
Route::delete('/payments/{id}', [MidtransController::class, 'destroy']);

Route::post('/midtrans/callback', [MidtransController::class, 'callback']);