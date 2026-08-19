<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitPinAttempts
{
    /**
     * Rate limit PIN validation attempts to prevent brute force attacks.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $scope  Rate-limit bucket: "view", "admin" (backend PIN), or "admin-password"
     */
    public function handle(Request $request, Closure $next, string $scope = 'view'): Response
    {
        $maxAttempts = config('access.pin_rate_limit_attempts', 5);
        $decayMinutes = config('access.pin_rate_limit_window', 15);

        if ($scope === 'admin' && ! $request->filled('pin')) {
            return $next($request);
        }

        if ($scope === 'admin-password' && (! $request->filled('password') || $request->filled('pin'))) {
            return $next($request);
        }

        $key = $this->rateLimitKey($scope, $request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->rateLimitedResponse($request, RateLimiter::availableIn($key), $scope);
        }

        $response = $next($request);

        if ($this->shouldCountFailedAttempt($request, $response, $scope)) {
            RateLimiter::hit($key, $decayMinutes * 60);
        }

        return $response;
    }

    private function rateLimitKey(string $scope, Request $request): string
    {
        $prefix = match ($scope) {
            'admin' => 'admin_pin_attempts',
            'admin-password' => 'admin_password_attempts',
            default => 'view_pin_attempts',
        };

        return $prefix.'_'.$request->ip();
    }

    private function rateLimitedResponse(Request $request, int $seconds, string $scope): Response
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        if ($scope === 'admin-password') {
            $message = __('auth.throttle', ['seconds' => $seconds]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->error($message, ['password' => [$message]], 429);
            }

            return redirect()->back()->with('error', $message);
        }

        $message = __('auth.too_many_pin_attempts', ['minutes' => $minutes]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->error($message, ['pin' => [$message]], 429);
        }

        return redirect()->back()->with('error', $message);
    }

    private function shouldCountFailedAttempt(Request $request, Response $response, string $scope): bool
    {
        if ($response->getStatusCode() === 302 && $request->session()->has('pin_error')) {
            return $scope !== 'admin-password';
        }

        if (! ($request->expectsJson() || $request->ajax()) || $response->getStatusCode() !== 422) {
            return false;
        }

        if ($scope === 'admin-password') {
            return $request->filled('password') && ! $request->filled('pin');
        }

        return $request->filled('pin');
    }
}
