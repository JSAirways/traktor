<?php

namespace App\Http\Middleware;

use App\Services\AuthenticationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountApproved
{
    protected AuthenticationService $authenticationService;

    public function __construct(AuthenticationService $authenticationService)
    {
        $this->authenticationService = $authenticationService;
    }

    /**
     * Handle an incoming request.
     * Ensure user account is approved before allowing backend access.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('welcome');
        }

        $user = auth()->user();
        [$isValid, , $redirectRoute] = $this->authenticationService->validateAccountStatus($user);

        if (!$isValid && $redirectRoute) {
            if ($redirectRoute === 'account-rejected') {
                return redirect()->route('account-rejected')
                    ->with('rejection_reason', $user->rejection_reason);
            }
            
            return redirect()->route($redirectRoute);
        }

        return $next($request);
    }
}
