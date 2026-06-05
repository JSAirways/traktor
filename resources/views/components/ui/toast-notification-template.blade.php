@props([])

{{--
    Toast Notification Template Component
    
    Wrapper component that renders the toast-notification component in template mode.
    This ensures client-side dynamic toasts use the exact same structure and logic
    as server-side toasts, eliminating duplication.
    
    This template is used by JavaScript showToast() function for client-side dynamic toasts.
--}}
<x-ui.toast-notification :asTemplate="true" />

