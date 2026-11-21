<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login and issue a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // PP §4.5: lock login for 15 minutes after 5 failed attempts.
        $throttleKey = Str::lower($validated['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);
            return response()->json(['message' => "Terlalu banyak percobaan login. Coba lagi dalam {$minutes} menit."], 429);
        }

        if (! Auth::attempt($validated)) {
            RateLimiter::hit($throttleKey, 15 * 60);
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }
        RateLimiter::clear($throttleKey);

        /** @var Admin $user */
        $user = Auth::user();

        // Revoke all previous tokens (single session)
        $user->tokens()->delete();

        DB::table('login_history')->insert([
            'idAdmin' => $user->getAuthIdentifier(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
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
        $history = DB::table('login_history')
            ->where('idAdmin', $request->user()->getAuthIdentifier())
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // `last_activity` (unix seconds) keeps the response shape the dashboard already reads.
        return response()->json($history->map(fn ($row) => [
            'id' => $row->id,
            'ip_address' => $row->ip_address,
            'user_agent' => $row->user_agent,
            'last_activity' => strtotime($row->created_at),
        ]));
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
