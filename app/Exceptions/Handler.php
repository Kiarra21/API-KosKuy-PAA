<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // noop
        });
    }

    /**
     * Convert an authentication exception into a response.
     *
     * Returns JSON for API requests (Accept: application/json or path starting with api/).
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $this->isApiRequest($request)) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Unauthenticated.'
            ], 401);
        }

        // Safely attempt to get redirect URL from the exception. The exception
        // implementation may call route('login') which throws if the named
        // route doesn't exist (common in API-only apps). Catch that and
        // fallback to a static URL.
        try {
            $redirect = $exception->redirectTo($request);
        } catch (\Throwable $e) {
            $redirect = null;
        }

        return redirect()->guest($redirect ?? url('/login'));
    }

    protected function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/');
    }
}
