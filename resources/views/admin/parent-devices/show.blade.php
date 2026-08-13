@extends('layouts.admin')

@section('content')
<x-admin.page-header
    :title="__('admin.device_prefix') . ' ' . ($device->device_name ?? __('admin.unnamed_device'))"
    title-id="deviceNameDisplay"
>
    <x-slot name="controls">
        <div></div>
        <a href="{{ route('admin.parent-devices.index') }}" class="btn btn-outline-success"><i class="bi bi-chevron-left me-1"></i>{{ __('admin.back_to_devices') }}</a>
    </x-slot>
</x-admin.page-header>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('admin.information') }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.parent-devices.update', $device) }}" id="deviceNameForm">
                    @csrf
                    @method('PUT')
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ __('admin.name_label') }} <span class="text-danger">*</span>:</dt>
                        <dd class="col-sm-7">
                            <div class="input-group" style="max-width: 300px;">
                                <input type="text" 
                                       class="form-control form-control-sm" 
                                       id="device_name" 
                                       name="device_name" 
                                       value="{{ $device->device_name ?? __('admin.unnamed_device') }}" 
                                       required 
                                       maxlength="255">
                                <button type="submit" class="btn bg-success text-white" title="{{ __('admin.save') }}">
                                    <i class="bi bi-floppy"></i>
                                </button>
                            </div>
                        </dd>
                    </dl>
                </form>
                <dl class="row mb-0 mt-3">
                    <dt class="col-sm-5">{{ __('admin.registered') }}:</dt>
                    <dd class="col-sm-7">{{ $device->registered_at->format('Y-m-d H:i:s') }}</dd>
                    
                    <dt class="col-sm-5">{{ __('admin.last_used') }}:</dt>
                    <dd class="col-sm-7">{{ $device->last_used_at ? $device->last_used_at->format('Y-m-d H:i:s') : __('admin.never') }}</dd>
                    
                    <dt class="col-sm-5">{{ __('admin.status') }}:</dt>
                    <dd class="col-sm-7">
                        @if($device->is_active)
                            <span class="badge bg-success">{{ __('admin.active') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ __('admin.inactive') }}</span>
                        @endif
                    </dd>
                </dl>
                
                @php
                    $browserInfo = $device->parseUserAgent();
                @endphp
                
                @if($browserInfo || $device->screen_resolution)
                    <hr class="my-3">
                    <div class="row align-items-center justify-content-between">
                        <i class="bi bi-display text-success col-sm-5 text-center" style="line-height: 1;font-size: 7rem;"></i>
                        <div class="col-sm-7">
                            @if($browserInfo)
                                <div class="mb-2">
                                    <small class="fw-bold d-block mb-1">{{ __('admin.browser') }}</small>
                                    <div class="small">{{ $browserInfo['browser'] }}</div>
                                </div>
                                <div class="mb-2">
                                    <small class="fw-bold d-block mb-1">{{ __('admin.operating_system') }}</small>
                                    <div class="small">{{ $browserInfo['os'] }}</div>
                                </div>
                            @endif
                            @if($device->screen_resolution)
                                <div>
                                    <small class="fw-bold d-block mb-1">{{ __('admin.screen_resolution') }}</small>
                                    <div class="small">{{ $device->screen_resolution }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('admin.device_capabilities') }}</h5>
                @include('admin.devices.partials.capability-badges', ['capabilities' => $device->capabilities])
            </div>
            <div class="card-body">
                @include('admin.devices.partials.capability-panel', ['capabilities' => $device->capabilities])
            </div>
        </div>
    </div>
    
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('admin.access') }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.parent-devices.child-visibility', $device) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-end mb-2">
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-success" onclick="selectAll()">{{ __('admin.select_all') }}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">{{ __('admin.deselect_all') }}</button>
                            </div>
                        </div>
                        
                        @foreach($allChildren as $child)
                            <div class="form-check mb-2">
                                <input class="form-check-input child-checkbox" 
                                       type="checkbox" 
                                       id="child_{{ $child->id }}" 
                                       name="child_visibility[{{ $child->id }}]" 
                                       value="1"
                                       {{ ($currentVisibility[$child->id] ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="child_{{ $child->id }}">
                                    <div class="d-flex align-items-center gap-2">
                                        @if($child->profile_picture)
                                            <img src="{{ asset('assets/profile-pictures/cats/' . $child->profile_picture) }}" 
                                                 alt="{{ $child->username }}" 
                                                 class="rounded-circle" 
                                                 style="width: 24px; height: 24px; object-fit: cover;">
                                        @endif
                                        <span>{{ $child->username }}</span>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">
                        {{ __('admin.save_changes') }}
                    </button>
                </form>
                
                <script>
                    function selectAll() {
                        document.querySelectorAll('.child-checkbox').forEach(cb => cb.checked = true);
                    }
                    function deselectAll() {
                        document.querySelectorAll('.child-checkbox').forEach(cb => cb.checked = false);
                    }
                </script>
            </div>
        </div>
    </div>
</div>

<script>
    // Update device name in header when form is submitted
    document.getElementById('deviceNameForm').addEventListener('submit', function(e) {
        const deviceNameInput = document.getElementById('device_name');
        const deviceNameDisplay = document.getElementById('deviceNameDisplay');
        
        // Update display immediately (optimistic update)
        // Use translations if available, otherwise fallback to English
        const devicePrefix = window.appTranslations?.admin?.device_prefix || 'Device:';
        deviceNameDisplay.textContent = devicePrefix + ' ' + deviceNameInput.value;
    });
</script>
@endsection

