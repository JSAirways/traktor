@props([
    'title' => config('app.name'),
    'user' => null,
    'greeting' => null,
])

@php
    // Get user's name or username for greeting
    $userName = $user ? ($user->username ?? 'User') : null;
    $greetingText = $greeting ?? ($userName ? "Hello {$userName}!" : 'Hello!');
    // Use full URL for email clients
    $logoUrl = url(asset('tractor.png'));
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #F8F9FA; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: #F8F9FA; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width: 600px; background-color: #FFFFFF; border: 1px solid #dee2e6; border-radius: 8px; padding: 40px;">
                    
                    <!-- Logo and App Name -->
                    <tr>
                        <td align="center" style="padding-bottom: 30px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center" style="vertical-align: middle;">
                                        <h2 style="font-size: 28px; font-weight: 600; color: #212529; margin: 0; display: inline-block; vertical-align: middle; padding-right: 5px;">Traktor</h2>
                                        <img src="{{ $logoUrl }}" alt="{{ __('common.app_logo') }}" style="height: 36px; width: auto; display: inline-block; vertical-align: middle;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Greeting -->
                    @if($greetingText)
                    <tr>
                        <td style="padding-bottom: 20px;">
                            <h5 style="font-size: 20px; font-weight: 500; color: #212529; margin: 0;">{{ $greetingText }}</h5>
                        </td>
                    </tr>
                    @endif
                    
                    <!-- Content Slot -->
                    <tr>
                        <td style="padding-bottom: 20px;">
                            {{ $slot }}
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding-top: 20px; border-top: 1px solid #dee2e6;">
                            <p style="font-size: 14px; color: #212529; margin: 0;">
                                Regards,<br>
                                The Traktor Team
                            </p>
                        </td>
                    </tr>
                </table>
                
                <!-- Copyright (outside content body) -->
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top: 20px;">
                    <tr>
                        <td align="center">
                            <p style="font-size: 12px; color: #212529; margin: 0;">
                                © {{ date('Y') }} {{ config('app.name') }}. {{ __('emails.all_rights_reserved') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

