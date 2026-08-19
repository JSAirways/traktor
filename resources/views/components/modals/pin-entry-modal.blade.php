@props([])

{{--
    PIN Entry Modal Component
    
    Modal for child PIN validation with auto-validation on 4 digits.
    
    Close button positioned outside modal-dialog like password login modal.
--}}
<!-- PIN Entry Modal with Close Button -->
<div class="modal fade" id="pinEntryModal" tabindex="-1" aria-hidden="true">
    {{-- Close button positioned at top right of screen (navbar area) - outside modal-dialog to avoid animation --}}
    {{-- tabindex="-1" prevents Bootstrap from focusing this button when modal opens --}}
    <button type="button" class="btn btn-outline-light border-0 position-fixed top-0 end-0 m-3 pin-entry-modal-close" data-bs-dismiss="modal" aria-label="Close" tabindex="-1">
        <i class="bi bi-x-lg fs-4"></i>
    </button>
    
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body">
                <div class="text-center mb-4">
                    <x-ui.user-avatar 
                        variant="normal"
                        image="{{ asset('assets/cats/Shop_Til_You_Drop_Shopping_Sticker_by_Pusheen.gif') }}"
                        title="{{ __('auth.enter_pin') }}"
                    />
                </div>
                <form id="pinEntryForm">
                    @csrf
                    <input type="hidden" name="username" id="pinEntryUsername" value="">

                    <x-forms.pin-input
                        inputId="pinEntryPin"
                        inputName="pin"
                        errorId="pinEntryError"
                        loadingId="pinEntryLoading"
                        :autofocus="true"
                    />
                </form>
            </div>
        </div>
    </div>
</div>

