<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new admin user.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'string', 'email', 'max:100', 'unique:tb_admin,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = Admin::create([
            'nama' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'user'    => $this->userResource($user),
            'token'   => $token,
        ], 201);
    }

    /**
     * Login and issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        /** @var Admin $user */
        $user = Auth::user();

        // Revoke all previous tokens (single session)
        $user->tokens()->delete();

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $user->getAuthIdentifier(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => json_encode(['login' => true]),
            'last_activity' => now()->timestamp,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'user'    => $this->userResource($user),
            'token'   => $token,
        ]);
    }

    public function loginHistory(Request $request): JsonResponse
    {
        return response()->json(DB::table('sessions')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('payload', 'like', '%"login":true%')
            ->orderByDesc('last_activity')
            ->limit(10)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity']));
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    /**
     * Return authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userResource($request->user()),
        ]);
    }

    /**
     * User resource array – keep sensitive fields out of response.
     */
    private function userResource(Admin $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->nama,
            'email' => $user->email,
            'role'  => 'admin',
        ];
    }
}
