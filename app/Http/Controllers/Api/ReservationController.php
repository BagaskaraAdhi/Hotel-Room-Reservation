<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA; // ANOTASI DITAMBAHKAN

class ReservationController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/reservations",
     * summary="Tampilkan semua reservasi",
     * description="Admin melihat semua reservasi. User hanya melihat reservasi miliknya sendiri.",
     * tags={"Reservation"},
     * security={{"sanctum":{}}},
     * @OA\Response(response=200, description="Berhasil mengambil data reservasi"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        $user = Auth::user();

        $query = reservation::with(['user', 'room']);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    /**
     * @OA\Get(
     * path="/api/reservations/{id}",
     * summary="Tampilkan detail reservasi",
     * description="Admin dapat melihat detail reservasi manapun. User hanya dapat melihat miliknya.",
     * tags={"Reservation"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID Reservasi", @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Berhasil mengambil detail reservasi"),
     * @OA\Response(response=403, description="Forbidden (tidak punya hak akses)"),
     * @OA\Response(response=404, description="Not Found (data tidak ditemukan)"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show($id)
    {
        $user = Auth::user();
        $reservation = reservation::with(['user', 'room'])->find($id);

        if (!$reservation) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        if ($user->role !== 'admin' && $reservation->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => $reservation]);
    }

    /**
     * @OA\Post(
     * path="/api/reservations",
     * summary="Buat reservasi baru",
     * description="User membuat reservasi untuk dirinya sendiri. Admin bisa membuat reservasi untuk user lain dengan menyertakan 'user_id'.",
     * tags={"Reservation"},
     * security={{"sanctum":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"room_id", "check_in_date", "check_out_date"},
     * @OA\Property(property="room_id", type="integer", example=1),
     * @OA\Property(property="check_in_date", type="string", format="date", example="2025-12-20"),
     * @OA\Property(property="check_out_date", type="string", format="date", example="2025-12-22"),
     * @OA\Property(property="extra_beds", type="integer", example=1),
     * @OA\Property(property="user_id", type="integer", description="[Khusus Admin] ID user yang melakukan reservasi.", example=2)
     * )
     * ),
     * @OA\Response(response=201, description="Reservasi berhasil dibuat"),
     * @OA\Response(response=422, description="Validation Error"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user->role === 'admin';

        $validated = $request->validate([
            'room_id' => 'required|exists:room,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after_or_equal:check_in_date',
            'extra_beds' => 'nullable|integer|min:0',
            'user_id' => $isAdmin ? 'required|exists:users,id' : '',
        ]);

        $room = Room::findOrFail($validated['room_id']);
        $extraBeds = $validated['extra_beds'] ?? 0;

        $defaultPrice = $room->defaultPrice;
        $extraBedPrice = $extraBeds * $room->defaultExtraBedPrice;
        $discountPercentage = $room->discount ? $room->discount / 100 : 0;

        $totalPrice = ($defaultPrice + $extraBedPrice) - ($defaultPrice * $discountPercentage);

        $reservation = reservation::create([
            'user_id' => $isAdmin ? $validated['user_id'] : $user->id,
            'room_id' => $validated['room_id'],
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'extra_beds' => $extraBeds,
            'extra_bed_price' => $extraBedPrice,
            'total_price' => $totalPrice,
            'status' => 'pending',
        ]);

        $reservation->load(['user', 'room']);

        return response()->json([
            'success' => true,
            'message' => 'Reservation created',
            'data' => [
                'username' => $reservation->user->username,
                'phone_number' => $reservation->user->phone_number ?? '',
                'email' => $reservation->user->email,
                'RoomNumber' => $reservation->room->RoomNumber,
                'RoomType' => $reservation->room->RoomType,
                'Capacity' => $reservation->room->Capacity,
                'reservation' => collect($reservation)->except(['id', 'user_id', 'room_id'])
            ]
        ]);
    }

    /**
     * @OA\Put(
     * path="/api/reservations/{id}",
     * summary="Update reservasi",
     * description="Admin bisa update semua field. User hanya bisa update check_in_date, check_out_date, dan extra_beds.",
     * tags={"Reservation"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID Reservasi", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * @OA\Property(property="check_in_date", type="string", format="date", example="2025-12-21"),
     * @OA\Property(property="extra_beds", type="integer", example=0),
     * @OA\Property(property="status", type="string", enum={"pending","confirmed","cancelled","CheckIn","CheckOut"}, description="[Khusus Admin] Status reservasi.")
     * )
     * ),
     * @OA\Response(response=200, description="Reservasi berhasil diupdate"),
     * @OA\Response(response=403, description="Forbidden"),
     * @OA\Response(response=404, description="Not Found"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $id)
    {
        $reservation = reservation::findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin' && $reservation->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $rules = $user->role === 'admin'
            ? [
                'user_id' => 'sometimes|exists:users,id',
                'room_id' => 'sometimes|exists:room,id',
                'check_in_date' => 'sometimes|date',
                'check_out_date' => 'sometimes|date|after_or_equal:check_in_date',
                'extra_beds' => 'sometimes|integer|min:0',
                'status' => 'sometimes|in:pending,confirmed,cancelled,CheckIn,CheckOut',
            ]
            : [
                'check_in_date' => 'sometimes|date',
                'check_out_date' => 'sometimes|date|after_or_equal:check_in_date',
                'extra_beds' => 'sometimes|integer|min:0',
            ];

        $validated = $request->validate($rules);

        $reservation->fill($validated);

        $room = $reservation->room;

        // Hitung harga extra bed
        $extraBeds = $reservation->extra_beds ?? 0;
        $reservation->extra_bed_price = $extraBeds * $room->defaultExtraBedPrice;

        // Ambil diskon nominal langsung dari database
        $nominalDiscount = $room->discount ?? 0;

        // Hitung harga total
        $reservation->total_price = ($room->defaultPrice + $reservation->extra_bed_price) - $nominalDiscount;

        $reservation->save();

        return response()->json([
            'success' => true,
            'message' => 'Reservation updated',
            'data' => $reservation
        ]);
    }

    /**
     * @OA\Delete(
     * path="/api/reservations/{id}",
     * summary="Hapus reservasi",
     * description="Menghapus reservasi. Hanya admin atau user yang memiliki reservasi yang bisa menghapus.",
     * tags={"Reservation"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID Reservasi", @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Reservasi berhasil dihapus"),
     * @OA\Response(response=403, description="Forbidden (bukan pemilik atau admin)"),
     * @OA\Response(response=404, description="Not Found"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy($id)
    {
        $reservation = reservation::with(['user', 'room'])->findOrFail($id);
        $user = Auth::user();

        if ($user->role !== 'admin' && $reservation->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $reservation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reservation deleted',
            'data' => [
                'username' => $reservation->user->username,
                'phone_number' => $reservation->user->phone_number ?? '',
                'email' => $reservation->user->email,
                'RoomNumber' => $reservation->room->RoomNumber,
                'RoomType' => $reservation->room->RoomType,
                'Capacity' => $reservation->room->Capacity,
                'reservation' => collect($reservation)->except(['id', 'user_id', 'room_id'])
            ]
        ]);
    }
}
