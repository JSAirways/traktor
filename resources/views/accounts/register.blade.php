@extends('layouts.frontend')

@section('title', __('forms.register_account') . ' - ' . __('common.app_name'))

@section('main-content')
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card bg-dark border-success text-light">
            <div class="card-body p-3">
                <x-ui.user-avatar 
                    image="{{ asset('assets/cats/Cat_Adopt_Sticker_by_Pusheen.gif') }}"
                    :title="__('forms.register_new_account')"
                    variant="normal"
                />

                <x-ui.flash-messages />

                <form method="POST" action="{{ route('register-account.store') }}" id="registerAccountForm" autocomplete="off">
                    @csrf
                    
                    <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
                    
                    <x-forms.form-field 
                        name="email" 
                        :label="__('common.email')" 
                        type="email"
                        :required="true"
                        :value="old('email')"
                        :autofocus="true"
                    />

                    <x-forms.form-field 
                        name="username" 
                        :label="__('common.username')" 
                        :required="true"
                        :value="old('username')"
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

                    <x-forms.form-field 
                        name="how_heard_about" 
                        :label="__('forms.how_heard_about')" 
                        :required="true"
                        :value="old('how_heard_about')"
                        maxlength="500"
                    />

                    <x-forms.profile-picture-selector 
                        name="cat_gif"
                        :currentValue="old('cat_gif', '')"
                        :pictures="$catGifs"
                        category="cats"
                    />

                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-success w-100">{{ __('forms.register') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
    @vite('resources/js/resources/shared/profile-picture-selector.js')
@endpush
@endsection

