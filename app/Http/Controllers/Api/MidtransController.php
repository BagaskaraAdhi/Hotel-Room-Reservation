<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Midtrans\Snap;
use Midtrans\Config;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;


class MidtransController extends Controller
{
    public function index()
    {
        return Payment::all();
    }

    public function store(Request $request)
    {
        try {
            // Validasi amount dan payment_method
            $request->validate([
                'amount' => 'required|numeric|min:1',
                'payment_method' => 'nullable|in:credit_card,debit_card,cash,online',
                'customer_name' => 'nullable|string',
            ]);

            // Buat payment record hanya dengan payment_method dan status (tidak ada amount)
            $payment = Payment::create([
                'payment_method' => $request->payment_method ?? 'online',
                'status' => 'Unpaid',
                'amount' => $request->amount, // Simpan amount untuk digunakan di Midtrans
            ]);

            // Konfigurasi Midtrans
            Config::$serverKey = config('midtrans.server_key');
            Config::$isProduction = config('midtrans.is_production');
            Config::$isSanitized = true;
            Config::$is3ds = true;

            // Parameter untuk Midtrans (order_id tetap id payment, gross_amount dari request)
            $params = [
                'transaction_details' => [
                    'order_id' => $payment->id,
                    'gross_amount' => $request->amount,
                ],
                'customer_details' => [
                    'first_name' => $request->customer_name ?? 'Customer',
                ],
                'expiry' => [
                    'start_time' => date("Y-m-d H:i:s O"), // 2025-06-06 15:00:00 +0700
                    'unit' => 'minute',
                    'duration' =>  2 // token berlaku 2 jam
                ]
            ];

            // Ambil Snap Token
            $snapToken = Snap::getSnapToken($params);

            return response()->json([
                'payment' => $payment,
                'snap_token' => $snapToken
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create payment',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        return Payment::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        // $user = Auth::user();
        // if (!$user || $user->role !== 'admin') {
        //     return response()->json(['message' => 'Forbidden'], 403);
        // }

        // try {
        //     $payment = Payment::findOrFail($id);
        //     $payment->update($request->only(['status', 'payment_method']));
        //     return response()->json($payment);
        // } catch (\Exception $e) {
        //     return response()->json([
        //         'error' => 'Something went wrong',
        //         'message' => $e->getMessage()
        //     ], 500);
        // }


        // menampilkan semua data user
        // Cek apakah user login dan rolenya admin
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden - Hanya admin yang bisa update'], 403);
        }

        // Kalau admin, lanjut update
        $payment = Payment::findOrFail($id);
        $payment->update($request->all());

        return response()->json(['message' => 'Data pembayaran berhasil diupdate', 'data' => $payment]);
    }

    public function destroy($id)
    {
        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->delete();

        return response()->json(['message' => 'Payment deleted']);
    }

    public function callback(Request $request)
    {
        $orderId = $request->order_id;
        $transactionStatus = $request->transaction_status;

        $payment = Payment::find($orderId);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if (in_array($transactionStatus, ['settlement', 'capture'])) {
            $payment->status = 'Paid';
            $payment->paid_at = Carbon::now();
        } elseif (in_array($transactionStatus, ['expire', 'cancel'])) {
            $payment->status = 'Failed';
        } elseif ($transactionStatus == 'refund') {
            $payment->status = 'Refunded';
        }

        $payment->save();

        return response()->json(['message' => 'Payment status updated']);
    }
}
