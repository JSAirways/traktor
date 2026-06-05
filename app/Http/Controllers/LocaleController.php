<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * LocaleController
 * 
 * Handles locale switching following Laravel's standard pattern:
 * - Updates session locale (for current session)
 * - Updates user preference if authenticated (persists across sessions)
 * - Redirects back to previous page
 * 
 * The SetLocale middleware will read the session locale on the next request.
 */
class LocaleController extends Controller
{
    /**
     * Switch application locale.
     * 
     * Updates session locale and optionally user preference if authenticated.
     * Redirects back to the previous page or a safe fallback.
     * 
     * This follows Laravel best practices: simple form POST → update session → redirect.
     * The middleware will pick up the locale change on the next request.
     */
    public function switch(Request $request): RedirectResponse
    {
        $request->validate([
            'locale' => ['required', 'string', 'in:' . implode(',', config('app.supported_locales', ['en']))],
            'redirect' => ['nullable', 'string'],
        ]);

        $locale = $request->input('locale');
        $supportedLocales = config('app.supported_locales', ['en']);

        // Validate locale is supported
        if (!in_array($locale, $supportedLocales, true)) {
            return redirect()->back()->withErrors(['locale' => 'Invalid locale']);
        }

        // Update session locale - this will be read by SetLocale middleware on next request
        $request->session()->put('locale', $locale);

        // Update user preference if authenticated (persists across sessions)
        if (Auth::check()) {
            $user = Auth::user();
            $user->locale = $locale;
            $user->save();
        }

        // Get safe redirect URL (use provided redirect or fallback)
        $redirectUrl = $this->getSafeRedirectUrl($request);

        // Redirect back with success message
        // The SetLocale middleware will read the session locale on the next request
        return redirect($redirectUrl)
            ->with('success', __('messages.language_changed') ?? 'Language changed successfully');
    }

    /**
     * Get a safe redirect URL, avoiding the locale switch route itself.
     */
    private function getSafeRedirectUrl(Request $request): string
    {
        // Priority 1: Use redirect parameter from form if provided and safe
        $redirect = $request->input('redirect');
        if ($redirect && $this->isSafeRedirect($redirect)) {
            // Parse URL and rebuild, removing cache-busting parameter
            $parsed = parse_url($redirect);
            $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
            $host = $parsed['host'] ?? '';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
            $path = $parsed['path'] ?? '';
            
            // Preserve query parameters but remove _t and locale (we'll use session)
            $query = [];
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $query);
                unset($query['_t']); // Remove cache-busting parameter
                unset($query['locale']); // Remove locale parameter (we use session)
            }
            
            $cleanUrl = $scheme . $host . $port . $path;
            
            // Add cache-busting parameter to ensure fresh page load with new locale
            $query['_t'] = time();
            
            if (!empty($query)) {
                $cleanUrl .= '?' . http_build_query($query);
            }
            
            if (isset($parsed['fragment'])) {
                $cleanUrl .= '#' . $parsed['fragment'];
            }
            
            return $cleanUrl;
        }

        // Priority 2: Use intended URL if set (e.g., from middleware)
        if ($request->session()->has('url.intended')) {
            $intended = $request->session()->pull('url.intended');
            if ($this->isSafeRedirect($intended)) {
                return $intended;
            }
        }

        // Priority 3: Use previous URL if safe
        $previous = url()->previous();
        if ($previous && $this->isSafeRedirect($previous)) {
            return $previous;
        }

        // Priority 4: Use HTTP_REFERER header if safe
        $referer = $request->header('referer');
        if ($referer && $this->isSafeRedirect($referer)) {
            return $referer;
        }

        // Fallback: Home route
        return route('home');
    }

    /**
     * Check if a redirect URL is safe (same domain, not locale switch route).
     */
    private function isSafeRedirect(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        // Parse URL
        $parsed = parse_url($url);
        
        // Must have a path
        if (!isset($parsed['path'])) {
            return false;
        }

        // Check if path is the locale switch route (prevent redirect loop)
        $path = $parsed['path'];
        if ($path === '/locale/switch' || str_ends_with($path, '/locale/switch')) {
            return false;
        }

        // If URL has a host, it must match our app URL or current request host
        if (isset($parsed['host'])) {
            $appUrl = config('app.url');
            $appHost = parse_url($appUrl, PHP_URL_HOST);
            $requestHost = request()->getHost();
            
            // Allow if host matches app URL or current request host (handles localhost, IPs, etc.)
            if ($parsed['host'] !== $appHost && $parsed['host'] !== $requestHost) {
                return false;
            }
        }

        return true;
    }
}
