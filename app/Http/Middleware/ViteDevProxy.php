<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proxy Vite HMR assets through Laravel in local+debug.
 *
 * Windows browsers (and Cursor port forwards) often reach artisan on a
 * remapped port such as :8001 but cannot open :5173 (ERR_CONNECTION_RESET).
 * Same-origin script tags plus this proxy keep the SPA mountable.
 */
class ViteDevProxy
{
    /**
     * @var list<string>
     */
    private const PATH_PREFIXES = [
        '/@vite',
        '/@react-refresh',
        '/@fs',
        '/@id',
        '/resources/js',
        '/resources/css',
        '/node_modules',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldProxy($request)) {
            return $next($request);
        }

        $origin = rtrim((string) config('sentria.vite.dev_server_url', 'http://127.0.0.1:5173'), '/');
        $url = $origin.$request->getRequestUri();

        try {
            $upstream = Http::withHeaders([
                'Accept' => $request->header('Accept', '*/*'),
            ])
                ->withOptions(['decode_content' => true])
                ->connectTimeout(2)
                ->timeout(60)
                ->send($request->method(), $url);
        } catch (ConnectionException) {
            abort(502, 'Vite dev server is not reachable.');
        }

        $response = response($upstream->body(), $upstream->status());

        foreach (['content-type', 'cache-control', 'etag'] as $header) {
            $value = $upstream->header($header);
            if (is_string($value) && $value !== '') {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }

    private function shouldProxy(Request $request): bool
    {
        if (app()->isProduction() || ! (bool) config('app.debug')) {
            return false;
        }

        if (! is_file(public_path('hot'))) {
            return false;
        }

        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');

        foreach (self::PATH_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
