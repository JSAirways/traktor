<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesProfilePicture;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use App\Services\ProfilePictureService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    use HandlesProfilePicture;

    public function __construct(
        protected ProfilePictureService $profilePictureService
    ) {
    }

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $supportedLocales = config('app.supported_locales', ['en']);
        return view('auth.register', compact('supportedLocales'));
    }

    /**
     * Display the register account view (frontend styled).
     */
    public function registerAccount(): View
    {
        $catGifs = $this->profilePictureService->getPicturesByCategory('cats');
        return view('accounts.register', compact('catGifs'));
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $profileData = $this->mapProfilePicture($validated['cat_gif'] ?? null);

        // Create user with 'pending' status (requires admin approval).
        // Role is hardcoded — never accept role from the request.
        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'how_heard_about' => $validated['how_heard_about'],
            'role' => 'user',
            'account_status' => 'pending',
            'locale' => $validated['locale'] ?? config('app.locale', 'en'),
        ] + $profileData);

        event(new Registered($user));

        return redirect()->route('welcome', ['registered' => '1'])
            ->with('success', __('messages.registration_submitted'));
    }
}
