<x-guest-layout>
    <div class="text-center">
        <div class="mb-4">
            <i class="bi bi-hourglass-split" style="font-size: 4rem; color: #ffc107;"></i>
        </div>
        <h2 class="mb-3">{{ __('account.account_pending_approval') }}</h2>
        <p class="text-muted mb-4">
            {{ __('account.registration_submitted') }}
        </p>
        <p class="text-muted mb-4">
            {{ __('account.pending_approval_message') }}
        </p>
        
        @if(session('success'))
            <x-ui.toast-notification type="success" message="{{ session('success') }}" />
        @endif

        <div class="d-flex flex-column flex-md-row justify-content-center gap-2">
            <a href="{{ route('welcome') }}" class="btn btn-success">
                {{ __('account.back_to_welcome') }}
            </a>
            <a href="{{ route('register') }}" class="btn btn-outline-secondary">
                {{ __('account.register_another_account') }}
            </a>
        </div>
    </div>
</x-guest-layout>

