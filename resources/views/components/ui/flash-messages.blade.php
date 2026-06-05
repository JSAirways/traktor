@props([])

@if(session('success'))
    <x-ui.toast-notification type="success" message="{{ session('success') }}" />
@endif

@if(session('status'))
    <x-ui.toast-notification type="success" message="{{ session('status') }}" />
@endif

@if(session('error'))
    <x-ui.toast-notification type="error" message="{{ session('error') }}" />
@endif

