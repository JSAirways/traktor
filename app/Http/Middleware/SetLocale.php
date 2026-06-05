<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetLocale Middleware
 * 
 * Sets the application locale following Laravel's standard pattern:
 * 1. URL query parameter (for email links - highest priority)
 * 2. Session locale (user's current preference)
 * 3. Authenticated user's locale preference (persists across sessions)
 * 4. Browser Accept-Language header (fallback)
 * 5. Application default locale
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     * 
     * Sets the application locale based on priority:
     * - URL parameter (for email links)
     * - Session (for current session preference)
     * - User preference (for authenticated users, persists across sessions)
     * - Browser header (fallback)
     * - Default locale
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('app.supported_locales', ['en']);
        $locale = null;

        // Priority 1: URL query parameter (for email links - highest priority)
        // This allows setting locale via URL like ?locale=de
        if ($request->has('locale')) {
            $urlLocale = $request->query('locale');
            if (in_array($urlLocale, $supportedLocales, true)) {
                $locale = $urlLocale;
                // Save to session so it persists after redirect
                if ($request->hasSession()) {
                    $request->session()->put('locale', $locale);
                }
            }
        }
        
        // Priority 2: Session locale (user's current preference for this session)
        if (!$locale && $request->hasSession()) {
            $sessionLocale = $request->session()->get('locale');
            if ($sessionLocale && in_array($sessionLocale, $supportedLocales, true)) {
                $locale = $sessionLocale;
            }
        }
        
        // Priority 3: Authenticated user's locale preference (persists across sessions)
        if (!$locale && Auth::check()) {
            $user = Auth::user();
            if ($user->locale && in_array($user->locale, $supportedLocales, true)) {
                $locale = $user->locale;
                // Sync to session for consistency
                if ($request->hasSession()) {
                    $request->session()->put('locale', $locale);
                }
            }
        }
        
        // Priority 4: Browser Accept-Language header (fallback)
        if (!$locale && $request->hasHeader('Accept-Language')) {
            $detectedLocale = $this->getLocaleFromHeader($request->header('Accept-Language'));
            if ($detectedLocale && in_array($detectedLocale, $supportedLocales, true)) {
                $locale = $detectedLocale;
            }
        }

        // Set the locale (or use default)
        app()->setLocale($locale ?: config('app.locale', 'en'));

        return $next($request);
    }

    /**
     * Extract locale from Accept-Language header.
     * 
     * Parses headers like "en-US,en;q=0.9" and returns "en"
     */
    private function getLocaleFromHeader(?string $header): ?string
    {
        if (!$header) {
            return null;
        }
        
        $locales = explode(',', $header);
        
        if (empty($locales)) {
            return null;
        }

        // Get first locale (highest priority)
        $firstLocale = trim(explode(';', $locales[0])[0]);
        
        // Extract language code (e.g., 'en' from 'en-US')
        if (strlen($firstLocale) >= 2) {
            return strtolower(substr($firstLocale, 0, 2));
        }

        return null;
    }
}
