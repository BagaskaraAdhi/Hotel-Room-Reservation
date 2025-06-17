<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index()
    {
        try {
            $rooms = Room::all();

            return response()->json([
                'success' => true,
                'message' => 'Room list retrieved successfully',
                'data' => $rooms
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve room list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $room = Room::find($id);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Room retrieved successfully',
                'data' => $room
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'RoomNumber' => 'required|string|max:10|unique:room',
                'RoomType' => 'required|string|max:50|in:single,double,twin,family,suite',
                'Capacity' => 'required|integer|min:1',
                'Status' => 'required|string|in:ready,maintenance',
                'imageShowRoom' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'defaultPrice' => 'required|numeric|min:0',
                'defaultExtraBedPrice' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0|max:100',
            ]);

            if ($request->hasFile('imageShowRoom')) {
                $image = $request->file('imageShowRoom');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('uploads/room'), $imageName);
                $validated['imageShowRoom'] = 'uploads/room/' . $imageName;
            }

            $room = Room::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Room created successfully',
                'data' => $room
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $room = Room::find($id);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }

            $validated = $request->validate([
                'RoomNumber' => 'sometimes|string|max:10|unique:room,RoomNumber,' . $room->id,
                'RoomType' => 'sometimes|string|in:single,double,twin,family,suite',
                'Capacity' => 'sometimes|numeric|min:1',
                'Status' => 'sometimes|string|in:ready,maintenance',
                'imageShowRoom' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'defaultPrice' => 'sometimes|numeric|min:0',
                'defaultExtraBedPrice' => 'sometimes|numeric|min:0',
                'discount' => 'nullable|numeric|min:0|max:100',
            ]);

            if ($request->hasFile('imageShowRoom')) {
                $image = $request->file('imageShowRoom');
                $imageName = time().'_'.$image->getClientOriginalName();
                $image->move(public_path('uploads/room'), $imageName);
                $validated['imageShowRoom'] = 'uploads/room/'.$imageName;
            }

            $room->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Room updated successfully',
                'data' => $room
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update room',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $room = Room::find($id);

            if (!$room) {
                return response()->json([
                    'success' => false,
                    'message' => 'Room not found'
                ], 404);
            }

            $room->delete();

            return response()->json([
                'success' => true,
                'message' => 'Room deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete room',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
