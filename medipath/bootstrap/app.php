<?php

use App\Exceptions\ImmutableRecordException;
use App\Http\Middleware\AdminIdleTimeout;
use App\Http\Middleware\ApprovedPharmacist;
use App\Http\Middleware\EnsureSessionFresh;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PendingStatusOnly;
use App\Http\Middleware\PreventSelfAction;
use App\Http\Middleware\RoleGate;
use App\Http\Middleware\VerifiedDoctor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsureSessionFresh::class,
        ]);

        $middleware->alias([
            'role' => RoleGate::class,
            'verified.doctor' => VerifiedDoctor::class,
            'pharmacist.approved' => ApprovedPharmacist::class,
            'pending.only' => PendingStatusOnly::class,
            'prevent.self' => PreventSelfAction::class,
            'admin.idle' => AdminIdleTimeout::class,
        ]);

        // The designed Login screen is delivered in Phase 1; until then a
        // guest is routed to the foundation page with a flash message.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            return $user && $user->status?->value === 'approved'
                ? route($user->dashboard_route)
                : route('pending.approval');
        });

        // Admin sessions get a shorter idle timeout in Phase 2; the global
        // session lifetime stays at PLAN's 120 minutes.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Sign in again.',
                    'code' => 'unauthenticated',
                    'retryable' => false,
                ], 401);
            }
        });

        $exceptions->render(function (ImmutableRecordException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'immutable_record',
                    'retryable' => false,
                ], 409);
            }
        });

        // Uniform retryable shape for API/JSON failures (client ApiError).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() || $e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            if ($status < 500) {
                return null;
            }

            return response()->json([
                'message' => 'A server error occurred. Nothing was saved client-side; retry the request.',
                'code' => 'server_error',
                'retryable' => true,
            ], $status);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() && $e->status === 429) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                    'code' => 'too_many_attempts',
                    'retryable' => true,
                    'retry_after' => (int) ($e->response?->headers->get('Retry-After') ?? 60),
                ], 429);
            }
        });
    })->create();
