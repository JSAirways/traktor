@props(['username', 'user' => null])

<x-layout.navbar variant="gallery" :username="$username">
    <x-slot name="center">
        <!-- Playlist navigation (previous/next when watching playlist video) -->
        <div id="playlistNavButtons" class="d-none d-flex justify-content-center gap-3 gap-md-4 align-items-center">
            <button type="button" id="prevVideoBtn" class="btn btn-transparent p-2" style="min-width: 44px; min-height: 44px;">
                <i class="bi bi-chevron-left text-light fs-3"></i>
            </button>
            <span id="playlistCounter" class="text-light fw-bold text-center" style="min-width: 60px;">- / -</span>
            <button type="button" id="nextVideoBtn" class="btn btn-transparent p-2" style="min-width: 44px; min-height: 44px;">
                <i class="bi bi-chevron-right text-light fs-3"></i>
            </button>
        </div>
    </x-slot>
    <x-slot name="actions">
        <x-ui.pwa-install-button />
        {{-- Return to Gallery button - visible when video playing or in playlist gallery view --}}
        <button type="button" id="returnToGalleryBtn" class="btn btn-outline-light border-0 d-none" title="{{ __('gallery.return_to_gallery') }}">
            <i class="bi bi-grid fs-3"></i>
        </button>
        {{-- Profile Selection button - always visible --}}
        <button type="button" id="profileSelectionBtn" class="btn border-0 p-0 navbar-profile-picture-btn" title="{{ __('gallery.profile_selection') }}">
            <x-ui.user-avatar 
                variant="tile" 
                :user="$user" 
                size="small" 
                :show-name="false" 
                mb="mb-0"
                border-color="light"
                :icon="$user ? null : 'bi-person-circle'"
            />
        </button>
        {{-- Settings button - opens offcanvas menu --}}
        <button type="button" class="btn btn-outline-light border-0" title="{{ __('gallery.settings') }}" data-bs-toggle="offcanvas" data-bs-target="#optionsMenuOffcanvas">
            <i class="bi bi-gear fs-3"></i>
        </button>
    </x-slot>
</x-navbar>

{{-- Always include admin password modal (same as profile-selection) --}}
<x-modals.admin-password-modal />

{{-- Options menu offcanvas --}}
<x-ui.options-menu-offcanvas variant="dark" :show-profile-selection="false" />

