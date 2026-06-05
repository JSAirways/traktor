<x-guest-layout>
    <div class="text-center">
        <div class="mb-4">
            <i class="bi bi-x-circle" style="font-size: 4rem; color: #dc3545;"></i>
        </div>
        <h2 class="mb-3">{{ __('account.account_rejected') }}</h2>
        <p class="text-muted mb-4">
            {{ __('account.registration_rejected') }}
        </p>
        
        @if(session('rejection_reason') || ($rejection_reason ?? null))
            <x-ui.toast-notification type="error" textAlign="text-start">
                <strong>{{ __('account.rejection_reason') }}</strong>
                <p class="mb-0 mt-2">{{ session('rejection_reason') ?? $rejection_reason ?? __('common.no_reason_provided') }}</p>
            </x-ui.toast-notification>
        @endif

        <p class="text-muted mb-4">
            {{ __('account.contact_administrator') }}
        </p>

        <div class="d-flex flex-column flex-md-row justify-content-center gap-2">
            <a href="{{ route('register') }}" class="btn btn-success">
                {{ __('account.register_again') }}
            </a>
            <a href="{{ route('welcome') }}" class="btn btn-outline-secondary">
                {{ __('account.back_to_welcome') }}
            </a>
        </div>
    </div>
</x-guest-layout>

