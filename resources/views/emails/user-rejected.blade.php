<x-emails.layout :title="__('emails.user_rejected_notification')" :user="$user">
    <!-- Content -->
    <p style="font-size: 16px; line-height: 1.6; color: #212529; margin: 0 0 20px 0;">
        {{ __('emails.user_rejected_intro') }}
    </p>
    
    @if(!empty($reason))
    <!-- Rejection Reason -->
    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0;">
        <p style="font-size: 14px; color: #212529; margin: 0 0 8px 0;"><strong>{{ __('emails.user_rejected_reason_label') }}:</strong></p>
        <p style="font-size: 14px; color: #212529; margin: 0 0 0 0;">{{ $reason }}</p>
    </div>
    @endif
    
    <!-- Footer Content -->
    <p style="font-size: 16px; line-height: 1.6; color: #212529; margin: 20px 0 0 0;">
        {{ __('emails.user_rejected_footer') }}
    </p>
</x-emails.layout>

