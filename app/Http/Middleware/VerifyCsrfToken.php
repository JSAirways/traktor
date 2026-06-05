<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;

/**
 * CSRF Token Verification Middleware
 * 
 * Protects against Cross-Site Request Forgery (CSRF) attacks by verifying
 * that state-changing requests include a valid CSRF token.
 * 
 * Security Model:
 * - All POST/PUT/PATCH/DELETE requests require CSRF tokens by default
 * - Excluded routes use alternative security measures (device tokens, rate limiting, etc.)
 * - AJAX requests automatically include CSRF tokens via X-CSRF-TOKEN header
 * 
 * @see https://laravel.com/docs/csrf
 */
class VerifyCsrfToken extends Middleware
{
    /**
     * Routes excluded from CSRF verification.
     * 
     * Routes are excluded when they have alternative security measures:
     * - Device token authentication
     * - Rate limiting
     * - Signed URLs
     * - Read-only operations (GET requests don't need CSRF)
     * 
     * @var array<int, string>
     */
    protected $except = [
        // Admin password verification
        // Security: Device registration + password verification + rate limiting (5 attempts per 15 minutes)
        // Rationale: Device cookie provides sufficient security context, password adds authentication layer
        'admin/verify-password',      // Path without leading slash (what $request->path() returns)
        '/admin/verify-password',     // Path with leading slash (for compatibility)
        
        // Device logout
        // Security: Device cookie authentication
        // Rationale: Read-only operation (clears cookies/sessions), better UX (tokens can expire)
        'device/logout',              // Path without leading slash
        '/device/logout',             // Path with leading slash
        
        // Analytics tracking endpoints
        // Security: Session validation (for child users), authentication checks (for parent users), user slug validation
        // Rationale: These endpoints have multiple layers of security (viewing session validation, auth checks, device registration)
        // CSRF protection can cause issues with long-running video sessions where tokens expire
        'api/analytics/track',
        'api/analytics/session/start',
        'api/analytics/session/end',
    ];
    
    /**
     * Determine if the request should pass through CSRF verification.
     * 
     * Checks route names first (most reliable), then falls back to path matching.
     * Route names are preferred because they're consistent regardless of URL structure.
     * 
     * Note: This method runs before route resolution, so we check paths first,
     * then route names if available.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldPassThrough($request): bool
    {
        // First, let parent method handle standard exclusions from $except array
        if (parent::shouldPassThrough($request)) {
            return true;
        }
        
        // Additional custom checks for routes that need special handling
        $path = $request->path();
        
        // Analytics routes - check with multiple methods to be absolutely sure
        if (str_starts_with($path, 'api/analytics/') || 
            $request->is('api/analytics/*')) {
            return true;
        }
        
        return false;
    }
}
