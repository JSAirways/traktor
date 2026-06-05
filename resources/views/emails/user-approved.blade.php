@php
    $displayableUrl = $welcomeUrl;
@endphp

<x-emails.layout :title="__('emails.user_approved_notification')" :user="$user">
    <!-- Content -->
    <p style="font-size: 16px; line-height: 1.6; color: #212529; margin: 0 0 20px 0;">
        {{ __('emails.user_approved_intro') }}
    </p>
    
    <!-- Welcome Button -->
    <x-emails.button :url="$welcomeUrl" :text="__('emails.user_approved_button')" color="success" />
    
    <!-- Fallback Link -->
    <p style="font-size: 14px; color: #212529; margin: 20px 0;">
        {{ __('emails.user_approved_fallback') }}
    </p>
    <p style="font-size: 14px; color: #212529; margin: 0 0 20px 0; word-break: break-all;">
        <a href="{{ $welcomeUrl }}" style="color: #0d6efd; text-decoration: underline;">{{ $displayableUrl }}</a>
    </p>
</x-emails.layout>

