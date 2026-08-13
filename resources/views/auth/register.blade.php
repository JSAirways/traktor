<x-guest-layout>
    <div class="mb-4">
        <h2 class="text-center">{{ __('forms.register') }}</h2>
        <p class="text-center text-muted">{{ __('welcome.create_new_account') }}</p>
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <x-ui.toast-notification type="success" message="{{ session('status') }}" />
    @endif

    @if (session('success'))
        <x-ui.toast-notification type="success" message="{{ session('success') }}" />
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div class="form-floating mb-3">
            <input id="name" class="form-control @error('name') is-invalid @enderror" type="text" name="name" value="{{ old('name') }}" placeholder=" " required autofocus autocomplete="name" />
            <label for="name">{{ __('common.name') }} *</label>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Username -->
        <div class="form-floating mb-3">
            <input id="username" class="form-control @error('username') is-invalid @enderror" type="text" name="username" value="{{ old('username') }}" placeholder=" " required autocomplete="username" />
            <label for="username">{{ __('common.username') }} *</label>
            @error('username')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Email Address -->
        <div class="form-floating mb-3">
            <input id="email" class="form-control @error('email') is-invalid @enderror" type="email" name="email" value="{{ old('email') }}" placeholder=" " required autocomplete="username" />
            <label for="email">{{ __('common.email') }} *</label>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Password -->
        <div class="form-floating mb-3">
            <input id="password" class="form-control @error('password') is-invalid @enderror"
                            type="password"
                            name="password"
                            placeholder=" "
                            required autocomplete="new-password" />
            <label for="password">{{ __('common.password') }} *</label>
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="form-floating mb-3">
            <input id="password_confirmation" class="form-control"
                            type="password"
                            name="password_confirmation"
                            placeholder=" "
                            required autocomplete="new-password" />
            <label for="password_confirmation">{{ __('common.confirm_password') }} *</label>
        </div>

        <!-- How did you hear about this app? -->
        <div class="form-floating mb-3">
            <input id="how_heard_about" class="form-control @error('how_heard_about') is-invalid @enderror" type="text" name="how_heard_about" value="{{ old('how_heard_about') }}" placeholder=" " required maxlength="500" autocomplete="off" />
            <label for="how_heard_about">{{ __('forms.how_heard_about') }} *</label>
            @error('how_heard_about')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <!-- Locale Selection -->
        @if(isset($supportedLocales) && count($supportedLocales) > 1)
            <div class="form-floating mb-3">
                <select id="locale" class="form-select @error('locale') is-invalid @enderror" name="locale">
                    @foreach($supportedLocales as $locale)
                        <option value="{{ $locale }}" {{ old('locale', app()->getLocale()) === $locale ? 'selected' : '' }}>
                            @php
                                $localeNames = [
                                    'en' => 'English',
                                    'de' => 'Deutsch',
                                    'fr' => 'Français',
                                    'es' => 'Español',
                                ];
                                echo $localeNames[$locale] ?? strtoupper($locale);
                            @endphp
                        </option>
                    @endforeach
                </select>
                <label for="locale">{{ __('common.language') }}</label>
                @error('locale')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif

        <div class="mb-3">
            <x-ui.toast-notification 
                type="info" 
                :autohide="false"
                icon="bi bi-info-circle"
                additional-classes="mb-3"
            >
                <small>{{ __('account.pending_approval_message') }}</small>
            </x-ui.toast-notification>
        </div>

        <div class="d-flex flex-column gap-2">
            <button type="submit" class="btn btn-success w-100">
                {{ __('forms.register') }}
            </button>

            <div class="text-center">
                <a class="text-decoration-none text-success" href="{{ route('welcome') }}">
                    {{ __('welcome.already_registered') }}
                </a>
            </div>
        </div>
    </form>
</x-guest-layout>

