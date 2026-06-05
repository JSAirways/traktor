@props([
    'url' => '#',
    'text' => 'Button',
    'color' => 'success', // success, primary, danger, etc.
])

@php
    $bgColor = match($color) {
        'success' => '#198754',
        'primary' => '#0d6efd',
        'danger' => '#dc3545',
        'warning' => '#ffc107',
        'info' => '#0dcaf0',
        default => '#198754',
    };
    
    $hoverColor = match($color) {
        'success' => '#157347',
        'primary' => '#0b5ed7',
        'danger' => '#bb2d3b',
        'warning' => '#ffb300',
        'info' => '#0aa2c0',
        default => '#157347',
    };
@endphp

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
    <tr>
        <td align="center" style="padding: 20px 0;">
            <a href="{{ $url }}" style="display: inline-block; padding: 12px 30px; background-color: {{ $bgColor }}; color: #FFFFFF; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: 500; border: none; cursor: pointer;">
                {{ $text }}
            </a>
        </td>
    </tr>
</table>

