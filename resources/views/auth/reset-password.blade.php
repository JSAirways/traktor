@extends('layouts.frontend')

@section('title', __('forms.reset_password') . ' - ' . __('common.app_name'))

@section('header-actions')
<a href="{{ route('welcome') }}" class="btn btn-outline-light border-0">
    <i class="bi bi-chevron-left me-1"></i> {{ __('common.back') }}
</a>
@endsection

@section('main-content')
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card bg-dark border-success text-light">
            <div class="card-body p-4">
                <x-ui.user-avatar 
                    image="{{ asset('assets/cats/Cat_People_Love_Sticker_by_Pusheen.gif') }}"
                    :title="__('forms.reset_password')"
                    variant="normal-lg"
                />
                
                <x-ui.flash-messages />

                <form method="POST" action="{{ route('password.store') }}">
                    @csrf

                    <!-- Password Reset Token -->
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <x-forms.form-field 
                        name="email" 
                        :label="__('common.email')" 
                        type="email"
                        :required="true"
                        :value="old('email', $request->email)"
                        :autofocus="true"
                    />

                    <x-forms.form-field 
                        name="password" 
                        :label="__('common.password')" 
                        type="password"
                        :required="true"
                    />

                    <x-forms.form-field 
                        name="password_confirmation" 
                        :label="__('common.confirm_password')" 
                        type="password"
                        :required="true"
                    />

                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-success w-100">{{ __('forms.reset_password') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
