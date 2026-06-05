<?php

namespace App\Http\Controllers;

use App\Http\Requests\ValidatePinRequest;
use App\Http\Requests\ValidatePinAjaxRequest;
use App\Services\UserLookupService;
use App\Services\ViewingSessionService;
use Illuminate\Http\Request;

class ViewingSessionController extends Controller
{
    public function __construct(
        protected UserLookupService $userLookupService,
        protected ViewingSessionService $viewingSessionService
    ) {
    }

    /**
     * Display PIN entry page.
     */
    public function showPinEntry(Request $request, string $slug)
    {
        $user = $this->userLookupService->findViewableUserBySlug($request, $slug);

        if (!$user) {
            return redirect()->route('welcome')
                ->with('error', __('messages.user_not_found'));
        }

        return view('pins.entry', [
            'username' => $user->username,
            'slug' => $user->slug,
            'requiresPin' => $user->hasPin()
        ]);
    }

    /**
     * Handle PIN validation for viewing page access.
     */
    public function validatePin(ValidatePinRequest $request)
    {
        $user = $this->userLookupService->findViewableUserByUsername($request, $request->username);

        // Use generic error message to prevent user enumeration
        // Don't reveal whether user exists or if PIN is wrong
        if (!$user) {
            $request->session()->put('pin_error', true);
            return redirect()->back()
                ->withInput()
                ->withErrors(['username' => __('messages.authentication_failed')]);
        }

        // If user has a PIN set, validate it
        if ($user->hasPin()) {
            // PIN is required if user has one set
            if (empty($request->pin)) {
                $request->session()->put('pin_error', true);
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['pin' => __('messages.authentication_failed')]);
            }

            // Verify PIN - use generic error message
            if (!$user->verifyPin($request->pin)) {
                $request->session()->put('pin_error', true);
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['pin' => __('messages.authentication_failed')]);
            }
        }

        // PIN is valid or not required - create viewing session
        $this->viewingSessionService->createSession($request, $user);

        // Clear PIN error flag
        $request->session()->forget('pin_error');

        // Redirect to viewing page
        return redirect()->route('gallery.show', ['slug' => $user->slug]);
    }

    /**
     * Validate PIN via AJAX (for modal).
     */
    public function validatePinAjax(ValidatePinAjaxRequest $request)
    {
        $user = $this->userLookupService->findViewableUserByUsername($request, $request->username);

        // Use generic error message to prevent user enumeration
        // Don't reveal whether user exists or if PIN is wrong
        if (!$user) {
            return response()->error(__('messages.authentication_failed'), null, 400);
        }

        // If user has a PIN set, validate it
        if ($user->hasPin()) {
            // Verify PIN - use generic error message
            if (!$user->verifyPin($request->pin)) {
                return response()->error(__('messages.authentication_failed'), null, 400);
            }
        } else {
            // User doesn't have PIN - should have been handled by direct access
            return response()->error(__('messages.authentication_failed'), null, 400);
        }

        // PIN is valid - create viewing session
        $this->viewingSessionService->createSession($request, $user);

        // Get intended URL from request or default to gallery
        $intendedUrl = $request->input('intended_url') ?? $request->input('redirect_url');
        
        // Validate and use intended URL if provided and valid
        if ($intendedUrl) {
            // Decode URL if it was encoded
            $decodedUrl = urldecode($intendedUrl);
            
            // Validate that the URL is for the same user (security check)
            $parsedUrl = parse_url($decodedUrl);
            if (isset($parsedUrl['path']) && str_contains($parsedUrl['path'], $user->slug)) {
                return response()->success(['redirect_url' => $decodedUrl], __('messages.authentication_success'));
            }
        }

        // Default redirect to gallery
        return response()->success(['redirect_url' => route('gallery.show', ['slug' => $user->slug])], __('messages.authentication_success'));
    }

    /**
     * Direct access to child's gallery without PIN (for children without PIN).
     */
    public function directAccess(Request $request, string $slug)
    {
        $user = $this->userLookupService->findViewableUserBySlug($request, $slug);

        if (!$user) {
            return redirect()->route('welcome')
                ->with('error', __('messages.user_not_found'));
        }

        // Only allow direct access if no PIN is set
        if ($user->hasPin()) {
            // Redirect to PIN entry if PIN is required
            // Use slug for the route
            return redirect()->route('pin-entry', ['slug' => $user->slug]);
        }

        // Create viewing session without PIN requirement
        $this->viewingSessionService->createSession($request, $user);

        // Redirect to viewing page
        return redirect()->route('gallery.show', ['slug' => $user->slug]);
    }
}




