<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthenticationService;
use App\Services\DeviceRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        protected AuthenticationService $authenticationService,
        protected DeviceRegistrationService $deviceService
    ) {
    }

    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        $formData = $this->authenticationService->getLoginFormData($request);

        return view('auth.login', $formData);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            // Check if device is registered for password-only login
            $device = $this->deviceService->getDeviceFromCookie($request);
            
            if ($device && $device->isActive()) {
                // Device login - use AuthenticationService (no rate limiting needed)
                [$success, $user, $redirectRoute] = $this->authenticationService->attemptLogin(
                    $request,
                    $request->boolean('remember'),
                    false // Not already authenticated
                );
            } else {
                // Normal login - LoginRequest handles rate limiting and authentication
                $request->authenticate();
                
                // Now validate account status (user is already authenticated)
                [$success, $user, $redirectRoute] = $this->authenticationService->attemptLogin(
                    $request,
                    $request->boolean('remember'),
                    true // Already authenticated by LoginRequest
                );
            }

            if (!$success) {
                // Handle account status redirects
                if ($redirectRoute === 'account-rejected') {
                    return redirect()->route('account-rejected')
                        ->with('rejection_reason', $user->rejection_reason);
                }
                
                return redirect()->route($redirectRoute);
            }

            // Login successful - regenerate session
            // Note: Viewing session is now stored in device registration, so regeneration won't affect it
            $request->session()->regenerate();

            // Redirect to home (profile selection) after login
            // Users can then access admin from there if needed
            return redirect()->route('home');
        } catch (ValidationException $e) {
            throw $e; // Re-throw validation exceptions
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $redirectUrl = $this->authenticationService->logout($request);
        return redirect($redirectUrl);
    }
}
