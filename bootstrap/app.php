<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (PostTooLargeException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'The uploaded file is too large. Please choose a smaller file.',
            ], 413);
        }

        return back()
            ->withInput($request->except('attachments'))
            ->withErrors([
                'attachments' => 'The uploaded file is too large. Please choose a smaller file.',
            ]);
    });
})->create();
