@props([])

{{--
    Pending Approval Modal Component
    
    Modal shown after user registration to inform them their account is pending approval.
    Uses static backdrop (cannot dismiss by clicking outside) and disabled keyboard (ESC).
    
    @uses x-modals.modal-base - Base modal structure
    @uses x-ui.user-avatar - User avatar display component
--}}
<!-- Pending Approval Modal with Close Button -->
<div class="modal fade" 
     id="pendingApprovalModal" 
     tabindex="-1" 
     data-bs-backdrop="static" 
     data-bs-keyboard="false"
     aria-hidden="true">
    {{-- Close button positioned at top right of screen (navbar area) - outside modal-dialog to avoid animation --}}
    <button type="button" class="btn btn-outline-light border-0 position-fixed top-0 end-0 m-3 pending-approval-modal-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}">
        <i class="bi bi-x-lg fs-4"></i>
    </button>
    
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border-success pb-4">
            <div class="modal-body">
                <div class="text-center mb-4">
                    <x-ui.user-avatar 
                        image="{{ asset('assets/cats/Working_Work_From_Home_Sticker_by_Pusheen.gif') }}"
                        title="{{ __('account.account_pending_approval') }}"
                        variant="normal"
                    />
                </div>

                <div class="text-center mb-4">
                    <p class="text-light mb-3">
                        {{ __('account.registration_submitted') }}
                    </p>
                    <p class="text-light mb-4">
                        {{ __('account.pending_approval_message') }}
                    </p>
                </div>

                <div class="d-flex flex-column gap-2">
                    <button type="button" class="btn btn-success w-100" data-bs-dismiss="modal">
                        {{ __('account.pending_approval_okay_button') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

