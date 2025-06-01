<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Midtrans\Snap;
use Midtrans\Config;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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
        $payment = Payment::findOrFail($id);
        $payment->update($request->all());
        return $payment;
    }

    public function destroy($id)
    {
        Payment::destroy($id);
        return response()->json(['message' => 'Payment deleted']);
    }

    public function callback(Request $request)
    {
        $serverKey = config('midtrans.server_key');
        $signatureKey = $request->signature_key;
        $orderId = $request->order_id;
        $statusCode = $request->status_code;
        $grossAmount = $request->gross_amount;

        $mySignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        if ($signatureKey !== $mySignature) {
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $payment = Payment::find($orderId);
        if (!$payment) return response()->json(['message' => 'Payment not found'], 404);

        if (in_array($request->transaction_status, ['settlement', 'capture'])) {
            $payment->status = 'Paid';
            $payment->paid_at = Carbon::now();
        } elseif (in_array($request->transaction_status, ['expire', 'cancel'])) {
            $payment->status = 'Failed';
        } elseif ($request->transaction_status == 'refund') {
            $payment->status = 'Refunded';
        }

        $payment->save();

        return response()->json(['message' => 'Payment status updated']);
    }
}
