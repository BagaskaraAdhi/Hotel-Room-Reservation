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
        return Payment::with('reservation.user', 'reservation.room')->get();
    }

    public function store(Request $request)
    {
        try {
            // Validasi input
            $request->validate([
                'reservation_id' => 'required|exists:reservation,id',
                'amount' => 'required|numeric|min:1',
                'payment_method' => 'nullable|in:credit_card,debit_card,cash,online',
                'customer_name' => 'nullable|string',
            ]);

            // Buat payment terkait reservation
            $payment = Payment::create([
                'reservation_id' => $request->reservation_id,
                'payment_method' => $request->payment_method ?? 'online',
                'status' => 'Unpaid',
                'amount' => $request->amount,
            ]);

            // Konfigurasi Midtrans
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            // Parameter Snap
            $params = [
                'transaction_details' => [
                    'order_id' => $payment->id,
                    'gross_amount' => $request->amount,
                ],
                'customer_details' => [
                    'first_name' => $request->customer_name ?? 'Customer',
                ],
                'expiry' => [
                    'start_time' => date("Y-m-d H:i:s O"),
                    'unit' => 'minute',
                    'duration' => 120
                ]
            ];

            $snapToken = \Midtrans\Snap::getSnapToken($params);

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
        $payment = Payment::with(['reservation.user', 'reservation.room'])->findOrFail($id);

        return response()->json([
            'payment' => $payment,
            'reservation' => [
                'check_in' => $payment->reservation->check_in_date,
                'check_out' => $payment->reservation->check_out_date,
                'total_price' => $payment->reservation->total_price,
                'room' => $payment->reservation->room->RoomType ?? null,
                'user' => $payment->reservation->user->username ?? null,
            ],
        ]);
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
