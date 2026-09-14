<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // microphone=(self) is required so Chamber microphones can list
        // inputs on this PC. camera and the rest stay disabled.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(self), geolocation=(), payment=(), usb=()',
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // In Vite HMR, assets are served from :5173 — not same-origin.
        // Without those hosts in script/style-src the SPA never mounts (blank page).
        // Impeccable live picker loads live.js from :8400; script-src must allow it in local+debug.
        $viteDev = ! app()->isProduction() && (bool) config('app.debug');
        $viteOrigins = 'http://localhost:5173 http://127.0.0.1:5173';
        $liveOrigins = 'http://localhost:8400 http://127.0.0.1:8400';
        $scriptSrc = $viteDev
            ? "'self' 'unsafe-inline' 'unsafe-eval' 'wasm-unsafe-eval' {$viteOrigins} {$liveOrigins}"
            : "'self' 'unsafe-inline' 'wasm-unsafe-eval'";
        $styleSrc = $viteDev
            ? "'self' 'unsafe-inline' {$viteOrigins}"
            : "'self' 'unsafe-inline'";
        $fontSrc = $viteDev
            ? "'self' data: {$viteOrigins}"
            : "'self' data:";
        $workerSrc = $viteDev
            ? "'self' blob: {$viteOrigins}"
            : "'self' blob:";

        $connectSrc = "'self' blob: ws: wss:";

        if ($viteDev) {
            $connectSrc .= " {$viteOrigins} ws://localhost:5173 ws://127.0.0.1:5173 http://localhost:* http://127.0.0.1:*";
        }

        $csp = implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "frame-src 'self' blob:",
            "object-src 'none'",
            "script-src {$scriptSrc}",
            "style-src {$styleSrc}",
            "img-src 'self' data: blob:",
            "font-src {$fontSrc}",
            "worker-src {$workerSrc}",
            "connect-src {$connectSrc}",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
