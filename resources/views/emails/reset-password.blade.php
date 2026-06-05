@php
    $expireMinutes = $expire ?? 60;
    $displayableUrl = $url;
@endphp

<x-emails.layout :title="__('emails.reset_password_notification')" :user="$user">
    <!-- Content -->
    <p style="font-size: 16px; line-height: 1.6; color: #212529; margin: 0 0 20px 0;">
        {{ __('emails.reset_password_intro') }}
    </p>
    
    <!-- Reset Button -->
    <x-emails.button :url="$url" :text="__('emails.reset_password_button')" color="success" />
    
    <!-- Fallback Link -->
    <p style="font-size: 14px; color: #212529; margin: 20px 0;">
        {{ __('emails.reset_password_fallback') }}
    </p>
    <p style="font-size: 14px; color: #212529; margin: 0 0 20px 0; word-break: break-all;">
        <a href="{{ $url }}" style="color: #0d6efd; text-decoration: underline;">{{ $displayableUrl }}</a>
    </p>
    
    <!-- Expire Text -->
    <p style="font-size: 14px; color: #212529; margin: 20px 0;">
        {{ __('emails.reset_password_expire', ['minutes' => $expireMinutes]) }}
    </p>
    
    <!-- Footer Content -->
    <p style="font-size: 16px; line-height: 1.6; color: #212529; margin: 20px 0 0 0;">
        {{ __('emails.reset_password_footer') }}
    </p>
</x-emails.layout>

