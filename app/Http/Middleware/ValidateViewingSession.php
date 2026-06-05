<?php

namespace App\Http\Middleware;

use App\Services\UserLookupService;
use App\Services\ViewingSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateViewingSession
{
    public function __construct(
        protected ViewingSessionService $viewingSessionService,
        protected UserLookupService $userLookupService
    ) {
    }

    /**
     * Handle an incoming request.
     * Check if session has valid viewing credentials for accessing child viewing pages.
     * 
     * Note: This middleware allows requests through even without a session.
     * Controllers are responsible for creating sessions when appropriate.
     * This follows Laravel's pattern of keeping middleware simple and letting controllers handle business logic.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get slug from route
        $slug = $request->route('slug');
        
        if (!$slug) {
            // No slug in route - let controller handle it
            return $next($request);
        }

        // Validate viewing session using device registration
        if (!$this->viewingSessionService->validateSession($request, $slug)) {
            // Session is invalid or expired
            // But first, check if user has a PIN - if not, allow through so controller can auto-create session
            $user = $this->userLookupService->findViewableUserBySlug($request, $slug);
            
            if ($user && !$user->hasPin()) {
                // User doesn't have a PIN - allow request through so controller can auto-create session
                return $next($request);
            }
            
            // User has PIN or user not found - require authentication
            $this->viewingSessionService->clearSession($request);
            return $this->unauthorizedResponse($request, __('messages.session_expired_enter_pin'));
        }

        return $next($request);
    }

    /**
     * Return appropriate unauthorized response based on request type.
     */
    protected function unauthorizedResponse(Request $request, string $message): Response
    {
        // For GET requests (browser navigation), always redirect
        // Only return JSON for POST/PUT/DELETE API requests or explicit AJAX requests
        $isApiRoute = $request->is('api/*');
        $isAjaxRequest = $request->ajax(); // Checks for X-Requested-With: XMLHttpRequest header
        $isGetRequest = $request->isMethod('GET');
        
        // For non-GET API/AJAX requests, return JSON error
        if (($isApiRoute || $isAjaxRequest) && !$isGetRequest) {
            return response()->error(__('messages.unauthorized_access'), null, 403);
        }

        // For GET requests (browser navigation), always redirect to profile selection page
        $slug = $request->route('slug');
        $intendedUrl = $request->fullUrl();
        
        // Redirect to home (profile selection) with the slug as a parameter
        // This allows the profile selection page to show PIN entry for that specific profile
        if ($slug) {
            return redirect()->route('home', ['requested_slug' => $slug, 'intended' => urlencode($intendedUrl)])
                ->with('error', $message)
                ->with('requested_slug', $slug);
        }

        // No slug - just redirect to home
        return redirect()->route('home')
            ->with('error', $message);
    }
}
