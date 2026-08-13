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
    
    <div class="row mb-3">
        <x-forms.pin-field 
            :currentPin="$generatedPin ?? null"
            :pinEnabled="true"
        />
        
        <div class="col-12 col-sm">
            <div class="form-floating">
                <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username') }}" placeholder=" " required autofocus>
                <label for="username">{{ __('common.username') }} *</label>
                @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
    
    <x-forms.profile-picture-selector 
        name="cat_gif"
        :currentValue="old('cat_gif', '')"
        :pictures="$catGifs"
        category="cats"
    />
    
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

