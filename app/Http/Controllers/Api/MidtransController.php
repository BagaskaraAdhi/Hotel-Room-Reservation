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


/**
 * @OA\Tag(name="Payments", description="API untuk mengelola pembayaran menggunakan Midtrans")
 */
class MidtransController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/payments",
     *     tags={"Payments"},
     *     summary="Menampilkan semua data pembayaran",
     *     @OA\Response(response=200, description="Daftar pembayaran berhasil ditampilkan")
     * )
     */
    public function index()
    {
        return Payment::all();
    }

    /**
     * @OA\Post(
     *     path="/api/payments",
     *     tags={"Payments"},
     *     summary="Membuat pembayaran dan mendapatkan Snap Token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", example=100000),
     *             @OA\Property(property="payment_method", type="string", example="credit_card"),
     *             @OA\Property(property="customer_name", type="string", example="John Doe")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Snap token berhasil dibuat"),
     *     @OA\Response(response=500, description="Gagal membuat pembayaran")
     * )
     */
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


    /**
     * @OA\Get(
     *     path="/api/payments/{id}",
     *     tags={"Payments"},
     *     summary="Menampilkan detail pembayaran berdasarkan ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID pembayaran",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Detail pembayaran ditemukan"),
     *     @OA\Response(response=404, description="Pembayaran tidak ditemukan")
     * )
     */
    public function show($id)
    {
        return Payment::findOrFail($id);
    }

    /**
     * @OA\Put(
     *     path="/api/payments/{id}",
     *     tags={"Payments"},
     *     summary="Update data pembayaran",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID pembayaran",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="Paid"),
     *             @OA\Property(property="payment_method", type="string", example="credit_card")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Pembayaran berhasil diupdate"),
     *     @OA\Response(response=404, description="Pembayaran tidak ditemukan")
     * )
     */
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
        if (!Auth::check() || Auth::User()->role !== 'admin') {
            return response()->json(['message' => 'Forbidden - Hanya admin yang bisa update'], 403);
        }

        // Kalau admin, lanjut update
        $payment = Payment::findOrFail($id);
        $payment->update($request->all());

        return response()->json(['message' => 'Data pembayaran berhasil diupdate', 'data' => $payment]);
    }

    /**
     * @OA\Delete(
     *     path="/api/payments/{id}",
     *     tags={"Payments"},
     *     summary="Menghapus pembayaran berdasarkan ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID pembayaran",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Pembayaran berhasil dihapus")
     * )
     */
    public function destroy($id)
    {
        // $user = Auth::user();

        // if (!$user || $user->role !== 'admin') {
        //     return response()->json(['message' => 'Forbidden'], 403);
        // }

        $payment = Payment::find($id);

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $payment->delete();

        return response()->json(['message' => 'Payment deleted']);
    }

    /**
     * @OA\Post(
     *     path="/api/payments/callback",
     *     tags={"Payments"},
     *     summary="Callback dari Midtrans untuk update status pembayaran",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"signature_key", "order_id", "status_code", "gross_amount", "transaction_status"},
     *             @OA\Property(property="signature_key", type="string"),
     *             @OA\Property(property="order_id", type="string"),
     *             @OA\Property(property="status_code", type="string"),
     *             @OA\Property(property="gross_amount", type="string"),
     *             @OA\Property(property="transaction_status", type="string", example="settlement")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Status pembayaran diperbarui"),
     *     @OA\Response(response=403, description="Signature tidak valid"),
     *     @OA\Response(response=404, description="Pembayaran tidak ditemukan")
     * )
     */
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
