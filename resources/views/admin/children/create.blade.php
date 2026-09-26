@extends('layouts.admin')

@section('content')
<x-admin.page-header :title="__('admin.add_child')">
    <x-slot name="controls">
        <div></div>
        <a href="{{ route('admin.children.index') }}" class="btn btn-outline-success"><i class="bi bi-chevron-left me-1"></i>{{ __('common.back') }}</a>
    </x-slot>
</x-admin.page-header>

<form method="POST" action="{{ route('admin.children.store') }}">
    @csrf

    <div class="d-flex align-items-start gap-3 mb-3">
        <x-forms.profile-picture-selector
            name="cat_gif"
            :currentValue="old('cat_gif', '')"
            :pictures="$catGifs"
            category="cats"
            :compact="true"
        />
        <div class="flex-grow-1">
            <div class="form-floating">
                <input type="text" class="form-control @error('profile_name') is-invalid @enderror" id="profile_name" name="profile_name" value="{{ old('profile_name') }}" placeholder=" " required autofocus>
                <label for="profile_name">{{ __('common.profile_name') }} *</label>
                @error('profile_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <x-forms.pin-field
            :currentPin="$generatedPin ?? null"
            :pinEnabled="true"
            columnClasses="col-12"
        />
    </div>

    <script type="application/json" data-pin-field>
    {
        "pinWrapperId": "pin-field-wrapper",
        "pinInputId": "pin",
        "pinAsteriskId": "pin-asterisk",
        "usePinCheckboxId": "use_pin",
        "currentPin": "{{ $generatedPin ?? '' }}"
    }
    </script>

    <button type="submit" class="btn btn-success w-100 w-md-auto">Create Child Account</button>
</form>
@endsection
