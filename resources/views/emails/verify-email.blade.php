@php
    $displayableUrl = $url;
@endphp

<x-emails.layout :title="__('emails.verify_email_address')" :user="$user">
    <!-- Content -->
    <p style="font-size: 16px; line-height: 1.6; color: #212529; margin: 0 0 20px 0;">
        {{ __('emails.verify_email_intro') }}
    </p>
    
    <!-- Verify Button -->
    <x-emails.button :url="$url" :text="__('emails.verify_email_button')" color="success" />
    
    <!-- Fallback Link -->
    <p style="font-size: 14px; color: #212529; margin: 20px 0;">
        {{ __('emails.verify_email_fallback') }}
    </p>
    <p style="font-size: 14px; color: #212529; margin: 0 0 20px 0; word-break: break-all;">
        <a href="{{ $url }}" style="color: #0d6efd; text-decoration: underline;">{{ $displayableUrl }}</a>
    </p>
    
    <!-- Footer Content -->
    <p style="font-size: 16px; line-height: 1.6; color: #212529; margin: 20px 0 0 0;">
        {{ __('emails.verify_email_footer') }}
    </p>
</x-emails.layout>

