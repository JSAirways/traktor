<x-guest-layout>
    <div class="mb-4">
        <h2 class="text-center">{{ __('auth.login') }}</h2>
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <x-ui.toast-notification type="success" message="{{ session('status') }}" />
    @endif

    @if (session('success'))
        <x-ui.toast-notification type="success" message="{{ session('success') }}" />
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        @if(isset($deviceRegistered) && $deviceRegistered && isset($parentEmail))
            <!-- Device Registered - Password Only Login -->
            <div class="mb-3">
                <x-ui.toast-notification 
                    type="info" 
                    :autohide="false"
                    icon="bi bi-info-circle"
                    additional-classes="mb-3"
                >
                    <small>{{ __('auth.device_registered_login_info') }}</small>
                </x-ui.toast-notification>
            </div>
            
            <input type="hidden" name="email" value="{{ $parentEmail }}">
        @else
            <!-- Email Address -->
            <div class="form-floating mb-3">
                <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" placeholder=" " required autofocus autocomplete="email" />
                <label for="email">{{ __('common.email') }} *</label>
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <!-- Password -->
        <div class="form-floating mb-3">
            <input id="password" class="form-control @error('password') is-invalid @enderror"
                            type="password"
                            name="password"
                            placeholder=" "
                            required autocomplete="current-password" />
            <label for="password">{{ __('common.password') }} *</label>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">
                    {{ __('auth.remember_me') }}
                </label>
            </div>
        </div>

        <div class="d-flex flex-column gap-2">
            <button type="submit" class="btn btn-success w-100">
                {{ __('common.login') }}
            </button>

            @if (Route::has('password.request'))
                <div class="text-center">
                    <a class="text-decoration-none text-success" href="{{ route('password.request') }}">
                        {{ __('auth.forgot_password_link') }}
                    </a>
                </div>
            @endif

            <div class="text-center">
                <a class="text-decoration-none text-success" href="{{ route('register') }}">
                    {{ __('auth.dont_have_account') }}
                </a>
            </div>
        </div>
    </form>
</x-guest-layout>

