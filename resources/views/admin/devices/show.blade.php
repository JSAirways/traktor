@extends('layouts.admin')

@section('content')
<x-admin.page-header :title="__('admin.device_details')">
    <x-slot name="subtitle">
        <p class="text-muted mb-0 small">{{ __('admin.parent') }}: <strong>{{ $device->parent->username }}</strong></p>
    </x-slot>
    <x-slot name="controls">
        <div></div>
        <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-success"><i class="bi bi-chevron-left me-1"></i>{{ __('admin.back_to_devices') }}</a>
    </x-slot>
</x-admin.page-header>

<div class="row">
    <div class="col-12 col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('admin.device_information') }}</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('admin.device_name_label') }}:</dt>
                    <dd class="col-sm-8">{{ $device->device_name ?? __('admin.unnamed_device') }}</dd>
                    
                    <dt class="col-sm-4">{{ __('admin.device_token') }}:</dt>
                    <dd class="col-sm-8">
                        <code class="small">{{ $device->device_token }}</code>
                    </dd>
                    
                    <dt class="col-sm-4">{{ __('admin.registered') }}:</dt>
                    <dd class="col-sm-8">{{ $device->registered_at->format('Y-m-d H:i:s') }}</dd>
                    
                    <dt class="col-sm-4">{{ __('admin.last_used') }}:</dt>
                    <dd class="col-sm-8">{{ $device->last_used_at ? $device->last_used_at->format('Y-m-d H:i:s') : __('admin.never') }}</dd>
                    
                    <dt class="col-sm-4">{{ __('admin.status') }}:</dt>
                    <dd class="col-sm-8">
                        @if($device->is_active)
                            <span class="badge bg-success">{{ __('admin.active') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ __('admin.inactive') }}</span>
                        @endif
                    </dd>
                </dl>
                
                <div class="mt-3">
                    @if($device->is_active)
                        <form method="POST" action="{{ route('admin.devices.deactivate', $device) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('{{ __('admin.confirm_deactivate_device') }}')">
                                <i class="bi bi-pause-circle me-1"></i>
                                {{ __('admin.deactivate') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.devices.activate', $device) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-play-circle me-1"></i>
                                {{ __('admin.activate') }}
                            </button>
                        </form>
                    @endif
                    
                    @php
                        $isCurrentDevice = isset($currentDeviceToken) && $currentDeviceToken === $device->device_token;
                        $deleteMessage = $isCurrentDevice 
                            ? __('admin.confirm_delete_current_device')
                            : __('admin.confirm_delete_device');
                    @endphp
                    <form method="POST" action="{{ route('admin.devices.destroy', $device) }}" class="d-inline" onsubmit="return confirm({{ json_encode($deleteMessage) }})">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-trash me-1"></i>
                            {{ __('common.delete') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('admin.child_visibility') }}</h5>
            </div>
            <div class="card-body">
                @if($allChildren->count() === 0)
                    <p class="text-muted mb-0">{{ __('admin.no_children_found_for_parent') }}</p>
                @else
                    <form method="POST" action="{{ route('admin.devices.child-visibility', $device) }}">
                        @csrf
                        @method('PUT')
                        
                        <p class="text-muted small mb-3">Select which children should be visible on this device:</p>
                        
                        <div class="list-group">
                            @foreach($allChildren as $child)
                                <label class="list-group-item d-flex align-items-center">
                                    <input type="checkbox" 
                                           name="child_ids[]" 
                                           value="{{ $child->id }}"
                                           class="form-check-input me-3"
                                           {{ in_array($child->id, $visibleChildren) ? 'checked' : '' }}>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold">{{ $child->username }}</div>
                                        @if(!$child->is_viewable)
                                            <small class="text-warning">{{ __('admin.viewing_disabled') }}</small>
                                        @endif
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        
                        <button type="submit" class="btn btn-success mt-3 w-100">
                            <i class="bi bi-save me-1"></i>
                            {{ __('admin.update_visibility') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
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
@endsection

