@extends('layouts.admin')

@section('content')
<x-admin.page-header :title="__('admin.edit_child') . ': ' . $child->username">
    <x-slot name="controls">
        <div></div>
        <a href="{{ route('admin.children.index') }}" class="btn btn-outline-success"><i class="bi bi-chevron-left me-1"></i>{{ __('common.back') }}</a>
    </x-slot>
</x-admin.page-header>

<form method="POST" action="{{ route('admin.children.update', $child) }}">
    @csrf
    @method('PUT')
    
    <div class="row mb-3">
        <x-forms.pin-field 
            :user="$child" 
            :currentPin="$currentPin ?? null"
        />
        
        <div class="col-12 col-sm">
            <div class="form-floating">
                <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $child->username) }}" placeholder=" " required autofocus>
                <label for="username">{{ __('common.username') }} *</label>
                @error('username')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
    
    @php
        // Get current profile picture - use profile_picture if available, otherwise fallback to cat_gif for backward compatibility
        $currentPicture = $child->profile_picture ?? ($child->cat_gif ?? '');
        // Only show if category is 'cats' (legacy cat gifs)
        if ($child->profile_picture_category && $child->profile_picture_category !== 'cats') {
            $currentPicture = '';
        }
    @endphp
    <x-forms.profile-picture-selector 
        name="cat_gif"
        :currentValue="$currentPicture"
        :pictures="$catGifs"
        category="cats"
    />
    
    {{-- Device Visibility --}}
    @php
        $user = auth()->user();
        $devices = $user->deviceRegistrations()->orderBy('last_used_at', 'desc')->get();
        $deviceVisibility = [];
        foreach ($devices as $device) {
            $visibility = \App\Models\DeviceChildVisibility::where('device_registration_id', $device->id)
                ->where('child_user_id', $child->id)
                ->first();
            $deviceVisibility[$device->id] = $visibility ? $visibility->is_visible : true; // Default to true if no setting exists
        }
    @endphp
    
    @if($devices->count() > 0)
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Device Access</h5>
            </div>
            <div class="card-body">
                
                <div class="d-flex justify-content-end mb-2">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-success" data-action="select-all-devices">Enable All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-action="deselect-all-devices">Disable All</button>
                    </div>
                </div>
                
                @foreach($devices as $device)
                    <div class="form-check mb-2">
                        <input class="form-check-input device-checkbox" 
                               type="checkbox" 
                               id="device_{{ $device->id }}" 
                               name="device_visibility[{{ $device->id }}]" 
                               value="1"
                               {{ ($deviceVisibility[$device->id] ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="device_{{ $device->id }}">
                            <strong>{{ $device->device_name ?? __('admin.unnamed_device') }}</strong>
                            @if($device->last_used_at)
                                <small class="text-muted"> - Last used {{ $device->last_used_at->diffForHumans() }}</small>
                            @endif
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <x-ui.toast-notification 
            type="info" 
            :autohide="false"
            icon="bi bi-info-circle"
            additional-classes="mb-3"
        >
            {{ __('admin.no_devices_registered_info') }} <a href="{{ route('welcome') }}" target="_blank" class="text-white text-decoration-underline fw-bold">{{ __('welcome.welcome_page') ?? 'welcome page' }}</a>.
        </x-ui.toast-notification>
    @endif
    
    <script type="application/json" data-pin-field>
    {
        "pinWrapperId": "pin-field-wrapper",
        "pinInputId": "pin",
        "pinAsteriskId": "pin-asterisk",
        "usePinCheckboxId": "use_pin",
        "currentPin": "{{ $currentPin ?? '' }}"
    }
    </script>
    
    <button type="submit" class="btn btn-success w-100 w-md-auto">Update Child</button>
</form>
@endsection

