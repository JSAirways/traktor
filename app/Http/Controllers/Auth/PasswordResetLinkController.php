<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Check if user exists before attempting to send reset link
        $user = \App\Models\User::where('email', $request->email)->first();
        
        // If user doesn't exist, return validation error
        if (!$user) {
            $errorMessage = __('passwords.user');
            
            // Return JSON for AJAX requests
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'message' => $errorMessage,
                    'errors' => ['email' => [$errorMessage]]
                ], 422);
            }
            
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => $errorMessage]);
        }
        
        // If user exists, update locale from session (prioritize current session locale for email)
        if ($request->session()->has('locale')) {
            $sessionLocale = $request->session()->get('locale');
            if (in_array($sessionLocale, config('app.supported_locales', ['en']))) {
                // Update user locale to match current session locale for email consistency
                $user->update(['locale' => $sessionLocale]);
            }
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Return JSON for AJAX requests
        if ($request->expectsJson() || $request->wantsJson()) {
            if ($status == Password::RESET_LINK_SENT) {
                return response()->json([
                    'message' => __($status),
                    'status' => 'sent'
                ]);
            } else {
                return response()->json([
                    'message' => __($status),
                    'errors' => ['email' => [__($status)]]
                ], 422);
            }
        }

        // Return redirect for regular form submissions
        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
