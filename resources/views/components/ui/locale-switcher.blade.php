@props([])

{{--
    Locale Switcher Component
    
    Displays a dropdown button with globe icon that allows users to switch languages.
    Shows country flags in the dropdown menu.
    Once a user selects a language, it's saved to their preference (if authenticated) or session.
    
    @example
    <x-ui.locale-switcher />
--}}

@php
    $supportedLocales = config('app.supported_locales', ['en']);
    $currentLocale = app()->getLocale();
    
    // Only show if there are multiple locales
    $shouldShow = count($supportedLocales) > 1;
    
    // Map locales to country flags (using emoji or flag codes)
    $localeFlags = [
        'en' => '🇬🇧', // UK flag
        'de' => '🇩🇪', // German flag
    ];
    
    $localeNames = [
        'en' => 'English',
        'de' => 'Deutsch',
    ];
@endphp

@if($shouldShow)
<div class="dropdown">
    <button 
        class="btn btn-outline-light border-0 dropdown-toggle" 
        type="button" 
        id="localeSwitcherBtn"
        data-bs-toggle="dropdown" 
        aria-expanded="false"
        title="{{ __('common.change_language') }}"
        aria-label="{{ __('common.change_language') }}"
        style="min-width: 44px; min-height: 44px;"
    >
        <i class="bi bi-globe fs-4"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="localeSwitcherBtn">
        @foreach($supportedLocales as $locale)
            <li>
                {{-- Simple form POST - Laravel handles redirect automatically --}}
                <form method="POST" action="{{ route('locale.switch') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="locale" value="{{ $locale }}">
                    {{-- Pass current URL so we can redirect back to it --}}
                    <input type="hidden" name="redirect" value="{{ url()->full() }}">
                    <button 
                        type="submit" 
                        class="dropdown-item d-flex align-items-center {{ $currentLocale === $locale ? 'active' : '' }}"
                    >
                        <span class="me-2" style="font-size: 1.2em;">{{ $localeFlags[$locale] ?? '🌐' }}</span>
                        <span>{{ $localeNames[$locale] ?? strtoupper($locale) }}</span>
                        @if($currentLocale === $locale)
                            <i class="bi bi-check ms-auto"></i>
                        @endif
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
</div>
@endif

