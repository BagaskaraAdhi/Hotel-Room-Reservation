<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MidtransController;

Route::get('/', function () {
    return view('welcome');
});

// cek signature key
// Route::get('/test-signature', function () {
//     $orderId = 4;
//     $statusCode = 200;
//     $grossAmount = 5000000;
//     $serverKey = config('midtrans.server_key');

//     $stringToHash = $orderId . $statusCode . $grossAmount . $serverKey;
//     return hash('sha512', $stringToHash);
// });
