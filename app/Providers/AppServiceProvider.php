<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('chat', function (Request $request) {
            $resting = fn () => response()->json(['message' => 'Asisten sedang istirahat. Silakan tanya langsung lewat WhatsApp.'], 429);

            // Project Plan §7: a 3-second gap between messages and 20 questions per hour per IP. The gap is
            // checked first so a message sent too soon does not use up the hour. The daily cap shared by all
            // visitors keeps the free Gemini quota safe.
            return [
                Limit::perSecond(1, 3)->by('gap:'.$request->ip())
                    ->response(fn () => response()->json(['message' => 'Tunggu 3 detik sebelum mengirim pesan berikutnya.'], 429)),
                Limit::perHour(20)->by('hour:'.$request->ip())->response($resting),
                Limit::perDay(config('services.gemini.daily_limit'))->by('chat-global')->response($resting),
            ];
        });
    }
}
