<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;



class RoomController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/room",
     * summary="Menampilkan semua kamar",
     * tags={"Room"},
     * security={{"sanctum":{}}},
     * @OA\Response(response=200, description="List kamar berhasil diambil")
     * )
     */
    public function index()
    {
        $rooms = Room::all();
        return response()->json([
            'success' => true,
            'message' => 'Room list retrieved successfully',
            'data' => $rooms
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/room/{id}",
     *     summary="Menampilkan detail kamar berdasarkan ID",
     *     tags={"Room"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detail kamar ditemukan"
     *     ),
     *     @OA\Response(response=404, description="Kamar tidak ditemukan")
     * )
     */
    public function show($id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Room retrieved successfully',
            'data' => $room
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/room",
     *     summary="Membuat kamar baru",
     *     tags={"Room"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"RoomNumber", "RoomType", "Capacity", "Status", "defaultPrice", "defaultExtraBedPrice"},
     *             @OA\Property(property="RoomNumber", type="string"),
     *             @OA\Property(property="RoomType", type="string", enum={"single","double","twin","family","suite"}),
     *             @OA\Property(property="Capacity", type="integer"),
     *             @OA\Property(property="Status", type="string", enum={"ready","maintenance"}),
     *             @OA\Property(property="defaultPrice", type="number"),
     *             @OA\Property(property="defaultExtraBedPrice", type="number")
     *             @OA\Property(property="discount", type="number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Kamar berhasil dibuat"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'RoomNumber' => 'required|string|max:10|unique:room',
            'RoomType' => 'required|string|max:50|in:single,double,twin,family,suite',
            'Capacity' => 'required|integer|min:1',
            'Status' => 'required|string|in:ready,maintenance',
            'imageShowRoom' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'defaultPrice' => 'required|numeric|min:0',
            'defaultExtraBedPrice' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
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
    }

    /**
     * @OA\Put(
     *     path="/api/room/{id}",
     *     summary="Update data kamar",
     *     tags={"Room"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="RoomNumber", type="string"),
     *             @OA\Property(property="RoomType", type="string", enum={"single","double","twin","family","suite"}),
     *             @OA\Property(property="Capacity", type="integer"),
     *             @OA\Property(property="Status", type="string", enum={"ready","maintenance"}),
     *             @OA\Property(property="defaultPrice", type="number"),
     *             @OA\Property(property="defaultExtraBedPrice", type="number")
     *             @OA\Property(property="discount", type="number")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Kamar berhasil diupdate")
     * )
     */
    public function update(Request $request, $id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        $validated = $request->validate([
            'RoomNumber' => 'sometimes|string|max:10|unique:room,RoomNumber,' . $room->id,
            'RoomType' => 'sometimes|string|in:single,double,twin,family,suite',
            'Capacity' => 'sometimes|integer|min:1',
            'Status' => 'sometimes|string|in:ready,maintenance',
            'imageShowRoom' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'defaultPrice' => 'sometimes|numeric|min:0',
            'defaultExtraBedPrice' => 'sometimes|numeric|min:0',
            'discount' => 'sometimes|nullable|numeric|min:0',
        ]);

        if ($request->hasFile('imageShowRoom')) {
            $image = $request->file('imageShowRoom');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->move(public_path('uploads/room'), $imageName);
            $validated['imageShowRoom'] = 'uploads/room/' . $imageName;
        }

        $room->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Room updated successfully',
            'data' => $room
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/room/{id}",
     *     summary="Hapus kamar berdasarkan ID",
     *     tags={"Room"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Kamar berhasil dihapus"
     *     ),
     *     @OA\Response(response=404, description="Kamar tidak ditemukan")
     * )
     */
    public function destroy($id)
    {
        $room = Room::find($id);
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        $room->delete();

        return response()->json(['success' => true, 'message' => 'Room deleted successfully']);
    }
}
