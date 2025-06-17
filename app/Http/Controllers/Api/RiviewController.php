<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reviews;
use OpenApi\Annotations as OA; // Jangan lupa tambahkan ini

class RiviewController extends Controller
{
    /**
     * @OA\Get(
     * path="/api/reviews",
     * summary="Tampilkan semua review",
     * description="Mengambil daftar lengkap semua review dari semua user.",
     * tags={"Review"},
     * security={{"sanctum":{}}},
     * @OA\Response(response=200, description="Daftar review berhasil diambil"),
     * @OA\Response(response=500, description="Server Error"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        try {
            $reviews = Reviews::with(['user', 'reservation'])->get();
            return response()->json([
                'success' => true,
                'message' => 'Reviews retrieved successfully',
                'data' => $reviews
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     * path="/api/reviews/{id}",
     * summary="Tampilkan detail review",
     * description="Mengambil detail satu data review berdasarkan ID-nya.",
     * tags={"Review"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID Review", @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Berhasil mengambil detail review"),
     * @OA\Response(response=404, description="Not Found (Review tidak ditemukan)"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show($id)
    {
        try {
            $review = Reviews::with(['user', 'reservation'])->find($id);
            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'message' => 'Review retrieved successfully',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     /**
     * @OA\Post(
     * path="/api/reviews",
     * summary="Buat review baru dengan user_id manual",
     * description="Membuat ulasan baru untuk sebuah reservasi. User ID diinput secara manual.",
     * tags={"Review"},
     * security={{"sanctum":{}}},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"user_id", "reservation_id", "rating"},
     * @OA\Property(property="user_id", type="integer", description="ID User yang memberikan review.", example=1),
     * @OA\Property(property="reservation_id", type="integer", description="ID Reservasi yang direview.", example=1),
     * @OA\Property(property="rating", type="integer", description="Rating dari 1 sampai 5.", example=5),
     * @OA\Property(property="comment", type="string", description="Komentar atau ulasan.", example="Sangat memuaskan!")
     * )
     * ),
     * @OA\Response(response=201, description="Review berhasil dibuat"),
     * @OA\Response(response=422, description="Validation Error (Data tidak valid)"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request)
    {
        try {
            // Sebaiknya: 'user_id' => auth()->id() untuk keamanan
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'reservation_id' => 'required|exists:reservation,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string',
            ]);
            $review = Reviews::create($validated);
            return response()->json([
                'success' => true,
                'message' => 'Review created successfully',
                'data' => $review
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     * path="/api/reviews/{id}",
     * summary="Update sebuah review",
     * description="Memperbarui data review. Idealnya, hanya user yang membuat review atau admin yang bisa melakukan ini.",
     * tags={"Review"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID Review", @OA\Schema(type="integer")),
     * @OA\RequestBody(
     * @OA\JsonContent(
     * @OA\Property(property="rating", type="integer", description="Rating dari 1 sampai 5.", example=4),
     * @OA\Property(property="comment", type="string", description="Komentar atau ulasan.", example="Cukup baik, tapi bisa lebih bersih.")
     * )
     * ),
     * @OA\Response(response=200, description="Review berhasil diupdate"),
     * @OA\Response(response=404, description="Not Found"),
     * @OA\Response(response=403, description="Forbidden (jika ada hak akses)"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $review = Reviews::find($id);
            if (!$review) {
                return response()->json(['success' => false, 'message' => 'Review not found'], 404);
            }

            // Idealnya, ada pengecekan hak akses di sini
            // if (auth()->id() !== $review->user_id && auth()->user()->role !== 'admin') {
            //     return response()->json(['message' => 'Forbidden'], 403);
            // }

            $validated = $request->validate([
                'rating' => 'sometimes|integer|min:1|max:5',
                'comment' => 'nullable|string',
            ]);
            $review->update($validated);
            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     * path="/api/reviews/{id}",
     * summary="Hapus sebuah review",
     * description="Menghapus review. Idealnya, hanya user yang membuat review atau admin yang bisa melakukan ini.",
     * tags={"Review"},
     * security={{"sanctum":{}}},
     * @OA\Parameter(name="id", in="path", required=true, description="ID Review", @OA\Schema(type="integer")),
     * @OA\Response(response=200, description="Review berhasil dihapus"),
     * @OA\Response(response=404, description="Not Found"),
     * @OA\Response(response=403, description="Forbidden (jika ada hak akses)"),
     * @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy($id)
    {
        try {
            $review = Reviews::find($id);
            if (!$review) {
                return response()->json(['success' => false, 'message' => 'Review not found'], 404);
            }

            // Idealnya, ada pengecekan hak akses di sini
            // if (auth()->id() !== $review->user_id && auth()->user()->role !== 'admin') {
            //     return response()->json(['message' => 'Forbidden'], 403);
            // }

            $review->delete();
            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
