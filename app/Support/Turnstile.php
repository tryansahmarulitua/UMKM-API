<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile check (Project Plan §4.5). Fails closed: an outage or a missing secret in
 * production rejects the request rather than letting it through unchecked. Without a secret outside
 * production (laptop, tests) the check is skipped so the app still runs locally.
 */
class Turnstile
{
    private const URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function lolos(?string $token, ?string $ip): bool
    {
        $secret = config('services.turnstile.secret');
        if (! $secret) {
            if (app()->environment('production')) {
                Log::error('TURNSTILE_SECRET_KEY is not set; checkout and chat are refused.');

                return false;
            }

            return true;
        }
        if (! $token) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post(self::URL, [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]);

            return $response->successful() && $response->json('success') === true;
        } catch (\Throwable $exception) {
            Log::warning('Turnstile verification failed', ['message' => $exception->getMessage()]);

            return false;
        }
    }
}
