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
            $validated = $request->validate([
                'reservation_id' => 'required|exists:reservation,id',
                'amount' => 'required|numeric|min:1',
                'payment_method' => 'required|in:credit_card,debit_card,cash,online',
                'customer_name' => 'nullable|string'
            ]);

            // Tentukan status awal
            $status = $validated['payment_method'] === 'cash' ? 'Paid' : 'Unpaid';

            // 1. Simpan data pembayaran
            $payment = Payment::create([
                'reservation_id' => $validated['reservation_id'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'status' => $status,
            ]);

            // 2. Kalau metode cash, langsung return tanpa Midtrans
            if ($validated['payment_method'] === 'cash') {
                return response()->json([
                    'success' => true,
                    'message' => 'Cash payment recorded successfully',
                    'payment' => $payment
                ]);
            }

            // 3. Jika online atau kartu, jalankan Midtrans
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');
            \Midtrans\Config::$isSanitized = true;
            \Midtrans\Config::$is3ds = true;

            $orderId = 'PAY-' . $payment->id . '-' . time();

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $validated['amount'],
                ],
                'customer_details' => [
                    'first_name' => $validated['customer_name'] ?? 'Customer',
                ],
                'expiry' => [
                    'start_time' => date("Y-m-d H:i:s O"),
                    'unit' => 'minute',
                    'duration' => 120
                ]
            ];

            $snapToken = \Midtrans\Snap::getSnapToken($params);

            return response()->json([
                'success' => true,
                'message' => 'Payment created and Midtrans Snap token generated',
                'payment' => $payment,
                'snap_token' => $snapToken
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment',
                'error' => $e->getMessage()
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
        try {
            // Validasi input
            $validated = $request->validate([
                'amount' => 'sometimes|numeric|min:1',
                'payment_method' => 'sometimes|in:credit_card,debit_card,cash,online',
                'status' => 'sometimes|in:Unpaid,Paid',
                'paid_at' => 'nullable|date',
            ]);

            // Ambil data payment berdasarkan ID
            $payment = Payment::findOrFail($id);

            // Update field yang ada di request
            $payment->update($validated);

            // Jika status pembayaran menjadi 'Paid', ubah status reservasi ke 'confirmed'
            if (isset($validated['status']) && $validated['status'] === 'Paid') {
                $payment->reservation()->update([
                    'status' => 'confirmed'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'payment' => $payment
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment',
                'error' => $e->getMessage()
            ], 500);
        }
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
