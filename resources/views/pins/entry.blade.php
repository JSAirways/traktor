@extends('layouts.frontend')

@section('title', __('auth.enter_pin') . ' - ' . __('common.app_name'))

@section('header-actions')
<x-ui.back-button href="{{ route('home') }}" :text="__('common.back')" icon="bi-arrow-left" />
@endsection

@section('main-content')
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card bg-dark border-success">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">{{ __('auth.enter_pin') }}</h2>
                <p class="text-center text-muted mb-4">{{ __('auth.enter_pin_description') }}</p>
                
                <x-ui.flash-messages />

                @php
                    // requiresPin is passed from controller
                    $requiresPin = $requiresPin ?? false;
                @endphp

                <form method="POST" action="{{ route('view.validate') }}" id="pinEntryFormPage">
                    @csrf
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" 
                               id="username" name="username" 
                               value="{{ $username ?? old('username') }}" 
                               placeholder=" "
                               readonly>
                        <label for="username">{{ __('common.username') }}</label>
                    </div>

                    <div class="form-floating mb-3">
                        <input type="password" 
                               class="form-control @error('pin') is-invalid @enderror" 
                               id="pin" name="pin" 
                               maxlength="4" 
                               pattern="[0-9]{4}" 
                               inputmode="numeric"
                               placeholder=" "
                               @if($requiresPin) required @endif
                               autofocus>
                        <label for="pin">{{ __('common.pin') }}{{ $requiresPin ? ' *' : '' }}</label>
                        @error('pin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @if(!$requiresPin)
                        <div class="form-text text-muted mb-3">{{ __('auth.no_pin_set') }}</div>
                    @endif

                    <button type="submit" class="btn btn-success w-100">{{ __('auth.access_videos') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Auto-focus PIN input and handle numeric input
    document.addEventListener('DOMContentLoaded', function() {
        const pinInput = document.getElementById('pin');
        if (pinInput) {
            pinInput.focus();
            pinInput.addEventListener('input', function(e) {
                // Only allow numbers
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });
        }
    });
</script>
@endpush
@endsection

