<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;
use Psy\Readline\Userland;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Hotel Room Reservation",
 *      description="API Documentation for Hotel Room Reservation",
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     summary="Register user baru",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","email","password","phone_number"},
     *             @OA\Property(property="username", type="string", example="john_doe"),
     *             @OA\Property(property="role", type="string", enum={"admin","user"}, example="user"),
     *             @OA\Property(property="phone_number", type="string", example="081234567890"),
     *             @OA\Property(property="email", type="string", example="john@gmail.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Berhasil register"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'username'      => 'required|string|max:50',
                'role'          => 'in:admin,user',
                'phone_number'  => 'required|string|max:15',
                'email'         => 'required|email|unique:users,email',
                'password'      => 'required|string|min:6',
            ]);

            $user = User::create([
                'username'     => $validated['username'],
                'role'         => $validated['role'] ?? 'user',
                'phone_number' => $validated['phone_number'],
                'email'        => $validated['email'],
                'password'     => Hash::make($validated['password']),
            ]);

            return response()->json(['user' => $user], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Login user",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", example="john@gmail.com"),
     *             @OA\Property(property="password", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Berhasil login"),
     *     @OA\Response(response=422, description="validation failed")
     * )
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string'
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (! $user || ! Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'message' => 'Login gagal',
                    'errors' => [
                        'email' => ['Email atau password salah.']
                    ]
                ], 422);
            }

            $token = $user->createToken('api_token', [], now()->addMinutes(120))->plainTextToken;

            return response()->json([
                'token' => $token,
                'user'  => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Logout user",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Berhasil logout")
     * )
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     summary="Ambil data user yang sedang login",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Data user saat ini")
     * )
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
