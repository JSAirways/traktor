@props([])

{{--
    Admin Password Modal Component
    
    Modal for admin access authentication.
    
    Close button positioned outside modal-dialog like password login modal.
--}}
<!-- Admin Password Modal with Close Button -->
<div class="modal fade" id="adminPasswordModal" tabindex="-1" aria-hidden="true">
    {{-- Close button positioned at top right of screen (navbar area) - outside modal-dialog to avoid animation --}}
    <button type="button" class="btn btn-outline-light border-0 position-fixed top-0 end-0 m-3 admin-password-modal-close" data-bs-dismiss="modal" aria-label="Close">
        <i class="bi bi-x-lg fs-4"></i>
    </button>
    
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body">
                <div class="text-center mb-4">
                    <x-ui.user-avatar 
                        variant="normal"
                        image="{{ asset('assets/cats/Suspicious_Cat_Sticker_by_Pusheen.gif') }}"
                        :title="__('auth.admin_access')"
                    />
                </div>
                <form method="POST" action="{{ route('admin.verify-password') }}" id="adminPasswordForm">
                    @csrf
                    
                    <div class="form-floating mb-3">
                        <input type="password" class="form-control @error('password') is-invalid @enderror" 
                               id="adminPassword" name="password" placeholder=" " required autofocus
                               value="{{ old('password', '') }}">
                        <label for="adminPassword">{{ __('common.password') }}</label>
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <div id="adminPasswordError" class="invalid-feedback d-none"></div>
                    </div>

                    <button type="submit" class="btn btn-success w-100">{{ __('auth.access_admin') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

