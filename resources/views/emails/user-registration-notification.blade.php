@php
    $displayableApproveUrl = $approveUrl;
    $displayableRejectUrl = $rejectUrl;
@endphp

<x-emails.layout :title="__('emails.user_registration_notification')">
    <!-- Content -->
    <p style="font-size: 16px; line-height: 1.6; color: #212529; margin: 0 0 20px 0;">
        {{ __('emails.user_registration_intro') }}
    </p>
    
    <!-- User Details -->
    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0;">
        <p style="font-size: 14px; color: #212529; margin: 0 0 8px 0;"><strong>{{ __('emails.user_registration_name') }}:</strong> {{ $user->username }}</p>
        <p style="font-size: 14px; color: #212529; margin: 0 0 8px 0;"><strong>{{ __('emails.user_registration_username') }}:</strong> {{ $user->username }}</p>
        <p style="font-size: 14px; color: #212529; margin: 0 0 8px 0;"><strong>{{ __('emails.user_registration_email') }}:</strong> {{ $user->email }}</p>
        <p style="font-size: 14px; color: #212529; margin: 0 0 0 0;"><strong>{{ __('emails.user_registration_how_heard') }}:</strong> {{ $howHeardAbout ?? $user->how_heard_about ?? '—' }}</p>
    </div>
    
    <!-- Action Buttons -->
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
            <td align="center" style="padding: 10px 0;">
                <a href="{{ $approveUrl }}" style="display: inline-block; padding: 12px 30px; background-color: #198754; color: #FFFFFF; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 500; border: none; cursor: pointer; margin-right: 10px;">
                    {{ __('emails.user_registration_approve_button') }}
                </a>
                <a href="{{ $rejectUrl }}" style="display: inline-block; padding: 12px 30px; background-color: #dc3545; color: #FFFFFF; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 500; border: none; cursor: pointer;">
                    {{ __('emails.user_registration_reject_button') }}
                </a>
            </td>
        </tr>
    </table>
    
    <!-- Fallback Links -->
    <p style="font-size: 14px; color: #212529; margin: 20px 0;">
        {{ __('emails.user_registration_fallback') }}
    </p>
    <p style="font-size: 14px; color: #212529; margin: 0 0 10px 0; word-break: break-all;">
        <strong>{{ __('emails.user_registration_approve_button') }}:</strong> <a href="{{ $approveUrl }}" style="color: #0d6efd; text-decoration: underline;">{{ $displayableApproveUrl }}</a>
    </p>
    <p style="font-size: 14px; color: #212529; margin: 0 0 20px 0; word-break: break-all;">
        <strong>{{ __('emails.user_registration_reject_button') }}:</strong> <a href="{{ $rejectUrl }}" style="color: #0d6efd; text-decoration: underline;">{{ $displayableRejectUrl }}</a>
    </p>
</x-emails.layout>

