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
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Hotel Room Reservation",
 *         version="1.0"
 *     ),
 *     @OA\Components(
 *         @OA\SecurityScheme(
 *             securityScheme="bearerAuth",
 *             type="http",
 *             scheme="bearer",
 *             bearerFormat="JWT"
 *         )
 *     )
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="API untuk autentikasi user dan admin"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     tags={"Auth"},
     *     summary="Registrasi akun baru",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username", "phone_number", "email", "password"},
     *             @OA\Property(property="username", type="string", example="alif"),
     *             @OA\Property(property="role", type="string", enum={"admin", "user"}, example="user"),
     *             @OA\Property(property="phone_number", type="string", example="081234567890"),
     *             @OA\Property(property="email", type="string", format="email", example="alif@gmail.com"),
     *             @OA\Property(property="password", type="string", format="password", example="alif1234")
     *         )
     *     ),
     *     @OA\Response(response=201, description="User berhasil diregistrasi"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function register(Request $request)
    {
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
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Auth"},
     *     summary="Login dan dapatkan token JWT (berlaku 2 jam)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="alif@gmail.com"),
     *             @OA\Property(property="password", type="string", example="alif1234")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login berhasil"),
     *     @OA\Response(response=401, description="Kredensial salah"),
     *     @OA\Response(response=422, description="Validasi gagal")
     * )
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // token berlaku 2 jam
        $token = $user->createToken('api_token', [], now()->addMinutes(120))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Auth"},
     *     summary="Logout user (hapus token saat ini)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logout berhasil"),
     *     @OA\Response(response=401, description="Unauthorized")
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
     *     tags={"Auth"},
     *     summary="Get data user yang sedang login",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="User data berhasil ditampilkan"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
