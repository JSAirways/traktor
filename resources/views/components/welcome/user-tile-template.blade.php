@props([])

{{-- Hidden template for user tiles - cloned and populated by JavaScript --}}
<template id="userTileTemplate">
    <div class="col-auto mb-4 text-center">
        <div class="d-flex flex-column align-items-center user-avatar-tile" 
             style="cursor: pointer;"
             data-bs-toggle="modal"
             data-bs-target="#passwordLoginModal"
             data-user-email=""
             data-user-username=""
             data-user-device-name=""
             data-user-profile-picture="">
            <div class="user-avatar-circle bg-dark border border-success text-light d-flex align-items-center justify-content-center overflow-hidden">
                <img src="" alt="{{ __('common.profile') }}" class="user-avatar-image" />
            </div>
            <h5 class="mt-2 mb-0 text-light"></h5>
            <small class="text-muted"></small>
        </div>
    </div>
</template>

{{-- Hidden template for "Other" option --}}
<template id="otherOptionTemplate">
    <div class="col-auto mb-4 text-center">
        <a href="/register-device" class="text-decoration-none d-flex flex-column align-items-center user-avatar-tile" style="cursor: pointer;">
            <div class="user-avatar-circle bg-dark border border-success text-light d-flex align-items-center justify-content-center">
                <i class="bi bi-plus fs-1 text-success"></i>
            </div>
            <h5 class="mt-2 mb-0 text-light">{{ __('welcome.other_option') }}</h5>
        </a>
    </div>
</template>

