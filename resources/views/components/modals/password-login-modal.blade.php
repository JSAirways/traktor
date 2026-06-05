@props([])

{{--
    Password Login Modal Component
    
    Modal for user login from welcome page with profile picture display.
    
    @uses x-modals.modal-base - Base modal structure
    @uses x-ui.user-avatar - User avatar display component
--}}
<!-- Password Login Modal with Close Button -->
<div class="modal fade" id="passwordLoginModal" tabindex="-1" aria-hidden="true">
    {{-- Close button positioned at top right of screen (navbar area) - outside modal-dialog to avoid animation --}}
    <button type="button" class="btn btn-outline-light border-0 position-fixed top-0 end-0 m-3 password-login-modal-close" data-bs-dismiss="modal" aria-label="Close">
        <i class="bi bi-x-lg fs-4"></i>
    </button>
    
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body">
                <div class="text-center mb-4">
        <x-ui.user-avatar 
            variant="profile"
            containerId="passwordLoginModalProfilePicture"
        />
        <h5 class="text-center text-light mb-4 mt-2" id="passwordLoginModalUsernameDisplay"></h5>
    </div>

    <form method="POST" action="{{ route('device.register') }}" id="passwordLoginModalForm">
        @csrf
        <input type="hidden" name="email" id="passwordLoginModalEmail">
        {{-- device_name is set to password-only login flag via JavaScript --}}
        <input type="hidden" name="device_name" id="passwordLoginModalDeviceName" value="">
        <input type="hidden" name="device_fingerprint" id="passwordLoginModalFingerprint">
        <input type="hidden" name="user_agent" id="passwordLoginModalUserAgent">
        <input type="hidden" name="screen_resolution" id="passwordLoginModalScreenResolution">
        <input type="hidden" name="capabilities" id="passwordLoginModalCapabilities">
        
        <div class="form-floating mb-3">
            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                   id="passwordLoginModalPassword" name="password" placeholder=" " required autofocus>
            <label for="passwordLoginModalPassword">{{ __('common.password') }}</label>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <div class="invalid-feedback d-none" id="passwordLoginModalError"></div>
        </div>

        <div class="d-flex flex-column gap-2">
            <button type="submit" class="btn btn-success w-100">{{ __('common.login') }}</button>
            <a href="{{ route('password.request') }}" class="btn btn-link text-light p-0 text-decoration-none text-center" data-store-password-form-state>{{ __('auth.forgot_password_link') }}</a>
        </div>
    </form>
            </div>
        </div>
    </div>
</div>



