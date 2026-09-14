<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ViteDevProxy;
use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Support\Env;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

/*
| Queue workers spawned by `queue:listen` inherit the parent process environment.
| Laravel's default dotenv repository is immutable, so later .env edits (for
| example AI_EMBEDDING_MODEL=hash-local) never reach those jobs. Use a mutable
| repository for worker processes so each job reads the current .env file.
*/
$queueCommands = ['queue:work', 'queue:listen', 'horizon', 'horizon:work'];
$artisanCommand = $_SERVER['argv'][1] ?? '';

if (in_array($artisanCommand, $queueCommands, true)) {
    $repository = RepositoryBuilder::createWithDefaultAdapters()
        ->addAdapter(PutenvAdapter::class)
        ->make();

    (new ReflectionClass(Env::class))
        ->getProperty('repository')
        ->setValue(null, $repository);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ViteDevProxy::class);

        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Sanctum is for external API consumers only. Stateful SPA domains stay empty
        // so the Inertia app continues to use Fortify session cookies.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->renderable(function (PostTooLargeException $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 413);
            }

            $fallback = match (true) {
                $request->is('resolutions/import', 'resolutions/import/*') => route('resolutions.import.create'),
                $request->is('ordinances/import', 'ordinances/import/*') => route('ordinances.import.create'),
                default => url('/'),
            };

            return redirect()
                ->to($request->headers->get('referer') ?: $fallback)
                ->with('error', 'legislation.import_too_large');
        });
    })->create();
