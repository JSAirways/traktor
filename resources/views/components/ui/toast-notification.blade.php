@props([
    'type' => 'info', // success, error, danger, warning, info
    'message' => null, // Main message content
    'autohide' => true, // Whether toast should auto-hide
    'icon' => null, // Icon class (e.g., 'bi bi-info-circle')
    'textAlign' => null, // Text alignment class (e.g., 'text-start') - accepts kebab-case 'text-align' or camelCase 'textAlign'
    'additionalClasses' => null, // Additional classes for the toast element
    'asTemplate' => false, // If true, renders as <template> element for JavaScript cloning (used by toast-notification-template component)
])

{{--
    Toast Notification Component
    
    Displays a Bootstrap toast notification with consistent styling and behavior.
    Toasts are automatically moved to the toast container and initialized by the layout scripts.
    
    When asTemplate is true, renders as a hidden <template> element for JavaScript to clone.
    This allows client-side dynamic toasts to use the same structure and logic as server-side toasts.
    
    @prop string $type - Toast type: 'success', 'error', 'danger', 'warning', 'info' (default: 'info')
    @prop string|null $message - The message content to display (required if slot is empty)
    @prop bool $autohide - Whether the toast should auto-hide (default: true)
    @prop string|null $icon - Icon class to display before message (e.g., 'bi bi-info-circle')
    @prop string|null $textAlign - Text alignment class for toast body (e.g., 'text-start')
    @prop string|null $additionalClasses - Additional CSS classes for the toast element (e.g., 'mb-3')
    @prop bool $asTemplate - If true, renders as <template> element for JavaScript cloning (default: false)
    
    @example Server-side toast
    <x-ui.toast-notification 
        type="success" 
        message="Operation completed successfully!" 
    />
    
    @example With icon and persistent
    <x-ui.toast-notification 
        type="info" 
        :autohide="false"
        icon="bi bi-info-circle"
        message="This is a persistent informational message."
    />
    
    @example With slot content (for complex HTML)
    <x-ui.toast-notification type="error" :autohide="false">
        <strong>Error:</strong> Something went wrong.
        <a href="#" class="text-white text-decoration-underline fw-bold">Click here</a> for more info.
    </x-ui.toast-notification>
    
    @example Template mode (for JavaScript cloning)
    <x-ui.toast-notification :asTemplate="true" />
--}}

@php
    // Map type to Bootstrap background class
    $bgClassMap = [
        'success' => 'bg-success',
        'error' => 'bg-danger',
        'danger' => 'bg-danger',
        'warning' => 'bg-warning',
        'info' => 'bg-info',
    ];
    
    $bgClass = $bgClassMap[$type] ?? 'bg-info';
    
    // Determine aria-live value based on type
    $ariaLive = in_array($type, ['success', 'error', 'danger', 'warning']) ? 'assertive' : 'polite';
    
    // Build classes
    $toastClasses = 'toast align-items-center text-white ' . $bgClass . ' border-0';
    if ($additionalClasses) {
        $toastClasses .= ' ' . $additionalClasses;
    }
    
    // Handle text alignment
    $bodyClasses = 'toast-body';
    if ($textAlign) {
        $bodyClasses .= ' ' . $textAlign;
    }
@endphp

@if($asTemplate)
    {{-- Template mode: Render as <template> element for JavaScript cloning --}}
    <template id="toastTemplate">
        <div 
            class="toast align-items-center text-white border-0" 
            role="alert" 
            aria-live="assertive" 
            aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('common.close') }}"></button>
            </div>
        </div>
    </template>
@else
    {{-- Normal mode: Render as regular toast notification --}}
    <div 
        class="{{ $toastClasses }}" 
        role="alert" 
        aria-live="{{ $ariaLive }}" 
        aria-atomic="true" 
        data-toast-type="{{ $type }}"
        @if(!$autohide)
            data-bs-autohide="false"
        @endif
    >
        <div class="d-flex">
            <div class="{{ $bodyClasses }}">
                @if($icon)
                    <i class="{{ $icon }} me-2"></i>
                @endif
                
                @if(isset($slot) && trim($slot))
                    {{ $slot }}
                @elseif($message)
                    {{ $message }}
                @endif
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="{{ __('common.close') }}"></button>
        </div>
    </div>
@endif

