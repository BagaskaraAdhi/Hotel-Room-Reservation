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
    /**
     * @OA\Get(
     * path="/api/payments",
     * summary="Tampilkan semua data pembayaran (Hanya Admin)",
     * description="Mengambil daftar lengkap semua transaksi pembayaran. Endpoint ini sebaiknya hanya dapat diakses oleh admin.",
     * tags={"Payment"},
     * security={{"sanctum":{}}},
     * @OA\Response(response=200, description="Daftar pembayaran berhasil diambil"),
     * @OA\Response(response=401, description="Unauthenticated"),
     * @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index()
    {
        return Payment::with('reservation.user', 'reservation.room')->get();
    }

    /**
     * @OA\Post(
     * path="/api/payments",
     * summary="Buat pembayaran dan dapatkan Snap Token Midtrans",
     * description="Membuat record pembayaran di database. Jika metode pembayaran 'cash', proses selesai. Jika 'online' atau kartu, akan menghasilkan snap_token dari Midtrans untuk ditampilkan di frontend.",
     * tags={"Payment"},
     * security={{"sanctum":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"reservation_id", "amount", "payment_method"},
     * @OA\Property(property="reservation_id", type="integer", example=1),
     * @OA\Property(property="amount", type="number", format="float", example=550000.00),
     * @OA\Property(property="payment_method", type="string", enum={"credit_card", "debit_card", "cash", "online"}, example="online"),
     * @OA\Property(property="customer_name", type="string", description="Opsional, nama pelanggan.", example="John Doe")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Pembayaran berhasil dibuat. Jika metode online, response akan berisi 'snap_token'.",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean", example=true),
     * @OA\Property(property="message", type="string"),
     * @OA\Property(property="payment", type="object"),
     * @OA\Property(property="snap_token", type="string", description="Hanya muncul jika metode pembayaran bukan 'cash'.")
     * )
     * ),
     * @OA\Response(response=422, description="Validation Error"),
     * @OA\Response(response=500, description="Server Error (Gagal membuat pembayaran atau token)")
     * )
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'reservation_id' => 'required|exists:reservation,id',
                'amount' => 'required|numeric|min:1',
                'payment_method' => 'required|in:credit_card,debit_card,cash,online',
                'customer_name' => 'nullable|string'
            ]);

            $status = $validated['payment_method'] === 'cash' ? 'Paid' : 'Unpaid';

            $payment = Payment::create([
                'reservation_id' => $validated['reservation_id'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'status' => $status,
            ]);

            // Jika cash, tidak perlu proses Midtrans
            if ($validated['payment_method'] === 'cash') {
                return response()->json([
                    'success' => true,
                    'message' => 'Cash payment recorded successfully',
                    'payment' => $payment
                ]);
            }

            // Midtrans setup
            Config::$serverKey = config('midtrans.server_key');
            Config::$isProduction = config('midtrans.is_production');
            Config::$isSanitized = true;
            Config::$is3ds = true;

            // Tetap buat order_id hanya untuk dikirim ke Midtrans (tidak disimpan ke DB)
            $orderId = 'PAY-' . $payment->id . '-' . time();

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $validated['amount'],
                ],
                'customer_details' => [
                    'first_name' => $validated['customer_name'] ?? Auth::user()->username,
                    'email' => Auth::user()->email,
                ],
                'expiry' => [
                    'start_time' => date("Y-m-d H:i:s O"),
                    'unit' => 'minute',
                    'duration' => 120
                ]
            ];

            $snapToken = Snap::getSnapToken($params);

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

    /**
     * @OA\Get(
     * path="/api/payments/{id}",
     * summary="Tampilkan detail pembayaran",
     * description="Mengambil detail satu data pembayaran berdasarkan ID.",
     * tags={"Payment"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID Pembayaran", @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Berhasil mengambil detail pembayaran"),
     * @OA\Response(response=404, description="Not Found"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
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

    /**
     * @OA\Put(
     * path="/api/payments/{id}",
     * summary="Update data pembayaran (Hanya Admin)",
     * description="Memperbarui status atau detail pembayaran. Jika status diubah menjadi 'Paid', status reservasi terkait akan menjadi 'confirmed'. Endpoint ini hanya untuk admin.",
     * tags={"Payment"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID Pembayaran", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * @OA\JsonContent(
     * @OA\Property(property="status", type="string", enum={"Unpaid", "Paid"}, example="Paid")
     * )
     * ),
     * @OA\Response(response=200, description="Pembayaran berhasil diupdate"),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=404, description="Not Found")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'amount' => 'sometimes|numeric|min:1',
                'payment_method' => 'sometimes|in:credit_card,debit_card,cash,online',
                'status' => 'sometimes|in:Unpaid,Paid',
                'paid_at' => 'nullable|date',
            ]);

            $payment = Payment::findOrFail($id);
            $payment->update($validated);

            if (isset($validated['status']) && $validated['status'] === 'Paid') {
                if ($payment->reservation) {
                    $payment->reservation->update(['status' => 'confirmed']);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment updated successfully',
                'payment' => $payment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Delete(
     * path="/api/payments/{id}",
     * summary="Hapus data pembayaran (Hanya Admin)",
     * description="Menghapus data pembayaran dari database. Endpoint ini hanya untuk admin.",
     * tags={"Payment"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID Pembayaran", @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Pembayaran berhasil dihapus"),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=404, description="Not Found")
     * )
     */
    public function destroy($id)
    {
        $payment = Payment::find($id);
        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }
        $payment->delete();
        return response()->json(['message' => 'Payment deleted']);
    }

    /**
     * @OA\Post(
     * path="/midtrans/callback",
     * summary="Callback Notifikasi Pembayaran Midtrans",
     * description="ENDPOINT INI BUKAN UNTUK DIGUNAKAN OLEH FRONTEND. Endpoint ini menerima notifikasi (webhook) dari server Midtrans untuk update status pembayaran secara otomatis.",
     * tags={"Midtrans Webhook"},
     * @OA\RequestBody(
     * required=true,
     * description="Payload yang dikirim oleh server Midtrans.",
     * @OA\JsonContent(
     * @OA\Property(property="transaction_time", type="string", example="2025-06-18 09:10:00"),
     * @OA\Property(property="transaction_status", type="string", example="settlement"),
     * @OA\Property(property="transaction_id", type="string", example="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"),
     * @OA\Property(property="status_message", type="string", example="midtrans payment notification"),
     * @OA\Property(property="status_code", type="string", example="200"),
     * @OA\Property(property="signature_key", type="string"),
     * @OA\Property(property="payment_type", type="string", example="gopay"),
     * @OA\Property(property="order_id", type="string", example="PAY-1-1749875460"),
     * @OA\Property(property="merchant_id", type="string"),
     * @OA\Property(property="gross_amount", type="string", example="550000.00"),
     * @OA\Property(property="fraud_status", type="string", example="accept"),
     * @OA\Property(property="currency", type="string", example="IDR")
     * )
     * ),
     * @OA\Response(response=200, description="Notifikasi berhasil diproses"),
     * @OA\Response(response=404, description="Order ID tidak ditemukan")
     * )
     */
    public function callback(Request $request)
    {
        // Konfigurasi server key untuk verifikasi signature
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');

        try {
            // Verifikasi notifikasi dari Midtrans (Best Practice)
            $notification = new \Midtrans\Notification();

            $transactionStatus = $notification->transaction_status;
            $orderId = $notification->order_id;

            // Cari payment berdasarkan order_id
            $payment = Payment::where('order_id', $orderId)->first();

            if (!$payment) {
                return response()->json(['message' => 'Payment with given order_id not found'], 404);
            }

            // Update status pembayaran
            if (in_array($transactionStatus, ['settlement', 'capture'])) {
                $payment->status = 'Paid';
                $payment->paid_at = Carbon::now();
                $payment->reservation->status = 'confirmed'; // Update status reservasi juga
                $payment->reservation->save();
            } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny'])) {
                $payment->status = 'Failed';
            } elseif ($transactionStatus == 'refund' || $transactionStatus == 'partial_refund') {
                $payment->status = 'Refunded';
            }

            $payment->save();
            return response()->json(['message' => 'Payment status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to process notification: ' . $e->getMessage()], 400);
        }
    }
}
