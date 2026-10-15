<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthenticated. Please provide a valid token.',
                'error' => 'Authentication required'
            ], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        // Handle API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    /**
     * Handle API exceptions
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleApiException($request, Throwable $exception)
    {
        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'status' => 401,
                'message' => 'Unauthenticated. Please provide a valid token.',
                'error' => 'Authentication required'
            ], 401);
        }

        if ($exception instanceof ThrottleRequestsException) {
            return response()->json([
                'status' => 429,
                'message' => 'Too many requests. Please try again later.',
                'error' => 'Rate limit exceeded'
            ], 429);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'status' => 404,
                'message' => 'The requested resource was not found.',
                'error' => 'Not Found'
            ], 404);
        }

        if ($exception instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'status' => 405,
                'message' => 'The HTTP method is not allowed for this resource.',
                'error' => 'Method Not Allowed'
            ], 405);
        }

        if ($exception instanceof RouteNotFoundException) {
            return response()->json([
                'status' => 404,
                'message' => 'The requested route was not found.',
                'error' => 'Route Not Found'
            ], 404);
        }

        // For other exceptions, return a generic error response
        return response()->json([
            'status' => 500,
            'message' => 'An internal server error occurred.',
            'error' => 'Internal Server Error'
        ], 500);
    }
}