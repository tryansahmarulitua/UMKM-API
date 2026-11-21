<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseCloudflareClientIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $remote = $request->server('REMOTE_ADDR');
        $clientIp = $request->header('CF-Connecting-IP');

        // Cloudflare Tunnel connects locally; never trust this header from a public origin.
        if (in_array($remote, ['127.0.0.1', '::1'], true) && filter_var($clientIp, FILTER_VALIDATE_IP)) {
            $request->headers->set('X-Forwarded-For', $clientIp);
        }

        return $next($request);
    }
}
