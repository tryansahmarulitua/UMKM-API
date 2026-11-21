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

            return [
                Limit::perMinute(30)->by($request->ip())->response($resting),
                Limit::perDay(config('services.gemini.daily_limit'))->by('chat-global')->response($resting),
            ];
        });
    }
}
