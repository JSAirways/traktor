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
                    
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <x-forms.profile-picture-selector
                            name="cat_gif"
                            :currentValue="old('cat_gif', '')"
                            :pictures="$catGifs"
                            category="cats"
                            :compact="true"
                        />
                        <div class="flex-grow-1">
                            <div class="form-floating">
                                <input type="text"
                                       class="form-control @error('profile_name') is-invalid @enderror"
                                       id="profile_name"
                                       name="profile_name"
                                       value="{{ old('profile_name') }}"
                                       placeholder=" "
                                       required
                                       autofocus
                                       autocomplete="off">
                                <label for="profile_name">{{ __('common.profile_name') }} *</label>
                                @error('profile_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <x-forms.form-field 
                        name="email" 
                        :label="__('common.email')" 
                        type="email"
                        :required="true"
                        :value="old('email')"
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

