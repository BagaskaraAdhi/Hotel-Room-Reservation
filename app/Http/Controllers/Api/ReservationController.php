<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    // View all (admin) or own (user)
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

    // Show by ID
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

    // Create (Admin or User)
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
        $discount = $room->discount;

        $totalPrice = ($defaultPrice + $extraBedPrice) - ($defaultPrice * $discount);

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

    // Update (Admin full, User limited)
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

        $extraBeds = $reservation->extra_beds;
        $reservation->extra_bed_price = $extraBeds * $room->defaultExtraBedPrice;
        $reservation->total_price = ($room->defaultPrice + $reservation->extra_bed_price) - ($room->defaultPrice * $room->discount);

        $reservation->save();

        return response()->json([
            'success' => true,
            'message' => 'Reservation updated',
            'data' => $reservation
        ]);
    }

    // Delete
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
