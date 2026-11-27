<?php

namespace App\Http\Controllers;

use App\Mail\OtpResetPassword;
use App\Models\Admin;
use App\Support\Turnstile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
            'email'     => ['required', 'email'],
            'password'  => ['required', 'string'],
            'turnstile' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! Turnstile::lolos($validated['turnstile'] ?? null, $request->ip())) {
            return response()->json(['message' => 'Verifikasi keamanan gagal. Coba lagi.'], 403);
        }

        $credentials = $request->only('email', 'password');

        // PP §4.5: lock login for 15 minutes after 5 failed attempts.
        $throttleKey = Str::lower($validated['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);
            return response()->json(['message' => "Terlalu banyak percobaan login. Coba lagi dalam {$minutes} menit."], 429);
        }

        if (! Auth::attempt($credentials)) {
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

    // ─────────────────────────── Reset Password via OTP ───────────────────────────

    /**
     * Step 1: Kirim OTP ke email admin.
     * POST /auth/forgot-password
     */
    public function kirimOtp(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $throttleKey = 'otp-request:' . Str::lower($validated['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $detik = RateLimiter::availableIn($throttleKey);
            return response()->json(['message' => "Terlalu banyak permintaan OTP. Coba lagi dalam {$detik} detik."], 429);
        }
        RateLimiter::hit($throttleKey, 10 * 60);

        $admin = Admin::where('email', $validated['email'])->first();
        // Selalu balas sukses agar tidak bocorkan apakah email terdaftar.
        if ($admin) {
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = now()->addMinutes(10);

            DB::table('password_reset_otps')
                ->where('email', $validated['email'])
                ->delete();

            DB::table('password_reset_otps')->insert([
                'email'      => $validated['email'],
                'otp_hash'   => Hash::make($otp),
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Mail::to($admin->email)->send(new OtpResetPassword($otp));
        }

        return response()->json(['message' => 'Jika email terdaftar, kode OTP telah dikirim.']);
    }

    /**
     * Step 2: Verifikasi OTP.
     * POST /auth/verify-otp
     */
    public function verifikasiOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string', 'size:6'],
        ]);

        $record = DB::table('password_reset_otps')
            ->where('email', $validated['email'])
            ->first();

        if (! $record) {
            return response()->json(['message' => 'OTP tidak ditemukan. Minta ulang OTP.'], 422);
        }

        if (now()->isAfter($record->expires_at)) {
            DB::table('password_reset_otps')->where('email', $validated['email'])->delete();
            return response()->json(['message' => 'OTP telah kadaluarsa. Minta ulang OTP.'], 422);
        }

        if ($record->attempts >= 5) {
            DB::table('password_reset_otps')->where('email', $validated['email'])->delete();
            return response()->json(['message' => 'Terlalu banyak percobaan. Minta ulang OTP.'], 429);
        }

        if (! Hash::check($validated['otp'], $record->otp_hash)) {
            DB::table('password_reset_otps')
                ->where('email', $validated['email'])
                ->increment('attempts');
            return response()->json(['message' => 'Kode OTP salah.'], 422);
        }

        // OTP valid — tandai sebagai terverifikasi (set otp_hash ke kosong) supaya step 3 bisa pakai
        DB::table('password_reset_otps')
            ->where('email', $validated['email'])
            ->update(['otp_hash' => '', 'updated_at' => now()]);

        return response()->json(['message' => 'OTP valid.']);
    }

    /**
     * Step 3: Reset password baru.
     * POST /auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('password_reset_otps')
            ->where('email', $validated['email'])
            ->first();

        // otp_hash kosong = OTP sudah diverifikasi step 2
        if (! $record || $record->otp_hash !== '' || now()->isAfter($record->expires_at)) {
            return response()->json(['message' => 'Sesi reset tidak valid. Mulai ulang proses.'], 422);
        }

        $admin = Admin::where('email', $validated['email'])->first();
        if (! $admin) {
            return response()->json(['message' => 'Email tidak ditemukan.'], 422);
        }

        $admin->update(['password' => Hash::make($validated['password'])]);
        $admin->tokens()->delete(); // paksa logout semua sesi aktif

        DB::table('password_reset_otps')->where('email', $validated['email'])->delete();

        return response()->json(['message' => 'Password berhasil diubah. Silakan login.']);
    }
}
