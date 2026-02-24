<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(HandleCors::class);
        $middleware->alias([
            'guest.document' => \App\Http\Middleware\GuestDocumentAccess::class,
            'document.access' => \App\Http\Middleware\DocumentAccess::class,
            'guest.document.status' => \App\Http\Middleware\GuestDocumentStatus::class,
            'supabase.auth' => \App\Http\Middleware\SupabaseAuth::class,
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
            'test.auth' => \App\Http\Middleware\TestAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
