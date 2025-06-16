<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Annotations as OA;
use Psy\Readline\Userland;

class AuthController extends Controller
{
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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
