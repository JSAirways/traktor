<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitPinAttempts
{
    /**
     * Handle an incoming request.
     * Rate limit PIN validation attempts to prevent brute force attacks.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'pin_attempts_' . $request->ip();
        $maxAttempts = config('access.pin_rate_limit_attempts', 5);
        $decayMinutes = config('access.pin_rate_limit_window', 15);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            return redirect()->back()
                ->with('error', "Too many PIN attempts. Please try again in " . ceil($seconds / 60) . " minutes.");
        }

        $response = $next($request);

        // If PIN validation failed, increment attempts
        if ($response->getStatusCode() === 302 && $request->session()->has('pin_error')) {
            RateLimiter::hit($key, $decayMinutes * 60);
        }

        return $response;
    }
}
