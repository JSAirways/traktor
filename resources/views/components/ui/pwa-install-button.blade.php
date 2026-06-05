{{--
    PWA Install Button Component
    
    Displays an install button for the Progressive Web App.
    Button is hidden by default and shown via JavaScript when app is installable.
    
    @prop string|null $variant - Button variant style (default: matches navbar style)
--}}
@props(['variant' => 'navbar'])

<button 
    type="button" 
    id="pwaInstallBtn" 
    class="btn btn-outline-light border-0 d-none" 
    title="{{ __('common.install_traktor_app') }}"
    aria-label="{{ __('common.install_traktor_app') }}"
>
    <i class="bi bi-download fs-4"></i>
    <span class="d-none d-sm-inline ms-1">{{ __('common.install_app') }}</span>
</button>


