<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Exception;
use Illuminate\Http\Request;
use App\Models\Reviews;

class RiviewController extends Controller
{
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

    public function store(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $review = Reviews::find($id);

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found'
                ], 404);
            }

            $validated = $request->validate([
                'user_id' => 'sometimes|exists:users,id',
                'reservation_id' => 'sometimes|exists:reservation,id',
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

    public function destroy($id)
    {
        try {
            $review = Reviews::find($id);

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found'
                ], 404);
            }

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
