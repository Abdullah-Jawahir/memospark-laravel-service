<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
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
        // Add CORS headers to all error responses for API requests
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $status = 500;
                $message = 'Server Error';
                
                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = 422;
                    $message = $e->getMessage();
                    $errors = $e->errors();
                    
                    $response = response()->json([
                        'message' => $message,
                        'errors' => $errors
                    ], $status);
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $status = $e->getStatusCode();
                    $message = $e->getMessage() ?: Response::$statusTexts[$status] ?? 'Error';
                    
                    $response = response()->json([
                        'message' => $message,
                        'error' => class_basename($e)
                    ], $status);
                } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $status = 404;
                    $message = 'Resource not found';
                    
                    $response = response()->json([
                        'message' => $message,
                        'error' => class_basename($e)
                    ], $status);
                } else {
                    // Generic error - include message in debug mode
                    $response = response()->json([
                        'message' => config('app.debug') ? $e->getMessage() : 'Server Error',
                        'error' => class_basename($e)
                    ], $status);
                }
                
                // Add CORS headers to error responses
                $allowedOrigins = [
                    'https://memo-spark-two.vercel.app',
                    'http://localhost:5173',
                    'http://localhost:3000',
                ];
                
                $origin = $request->header('Origin');
                if (in_array($origin, $allowedOrigins)) {
                    $response->headers->set('Access-Control-Allow-Origin', $origin);
                    $response->headers->set('Access-Control-Allow-Credentials', 'true');
                    $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                    $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, X-Requested-With, Origin');
                }
                
                return $response;
            }
        });
    })->create();
