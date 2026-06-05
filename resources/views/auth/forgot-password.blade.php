@extends('layouts.frontend')

@section('title', __('forms.reset_password') . ' - ' . __('common.app_name'))

@section('header-actions')
<a href="{{ route('welcome') }}" class="btn btn-outline-light border-0" data-restore-password-modal>
    <i class="bi bi-chevron-left me-1"></i> {{ __('common.back') }}
</a>
@endsection

@push('scripts')
    @vite('resources/js/resources/accounts/forgot-password.js')
@endpush

@section('main-content')
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card bg-dark border-success text-light">
            <div class="card-body p-4">
                <x-ui.user-avatar 
                    image="{{ asset('assets/cats/Cat_Love_Sticker_by_Pusheen.gif') }}"
                    :title="__('forms.reset_password')"
                    variant="normal"
                />
                <p class="text-center mb-4">{{ __('forms.enter_email_for_reset') }}</p>
                
                <x-ui.flash-messages />

                <form method="POST" action="{{ route('password.email') }}" id="forgotPasswordForm">
                    @csrf
                    
                    <x-forms.form-field 
                        name="email" 
                        :label="__('common.email')" 
                        type="email"
                        :required="true"
                        :value="old('email')"
                        :autofocus="true"
                    />

                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-success w-100">{{ __('forms.send_password_reset_link') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
