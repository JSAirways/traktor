@extends('layouts.admin')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
    <h2 class="mb-0">{{ __('admin.my_devices') }}</h2>
</div>

@if($devices->count() === 0)
    <x-ui.toast-notification 
        type="info" 
        :autohide="false"
        icon="bi bi-info-circle"
        message="{{ __('admin.no_devices_registered_yet') }}"
    />
@else
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('admin.device_name_label') }}</th>
                    <th>{{ __('admin.enabled_children') }}</th>
                    <th>{{ __('admin.last_used') }}</th>
                    <th>{{ __('admin.status') }}</th>
                    <th>{{ __('admin.capability_summary') }}</th>
                    <th class="text-end">{{ __('admin.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($devices as $device)
                    <tr class="align-middle">
                        <td class="align-middle">
                            <strong>{{ $device->device_name ?? __('admin.unnamed_device') }}</strong>
                        </td>
                        <td class="align-middle">
                            @php
                                $enabledChildren = $device->childVisibility()->where('is_visible', true)->count();
                                $totalChildren = auth()->user()->children()->count();
                                $badgeClass = $enabledChildren > 0 ? 'bg-success' : 'bg-warning';
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $enabledChildren }}/{{ $totalChildren }}</span>
                        </td>
                        <td class="align-middle">
                            @if($device->last_used_at)
                                {{ $device->last_used_at->diffForHumans() }}
                            @else
                                <span class="text-muted">{{ __('admin.never') }}</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            @if($device->is_active)
                                <span class="badge bg-success">{{ __('admin.active') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('admin.inactive') }}</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            @include('admin.devices.partials.capability-badges', ['capabilities' => $device->capabilities])
                        </td>
                        <td class="align-middle text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <a href="{{ route('admin.parent-devices.show', $device) }}" class="btn btn-outline-success border-0" title="{{ __('admin.manage') }} {{ __('admin.devices') }}">
                                    <i class="bi bi-gear"></i>
                                </a>
                                @if($device->is_active)
                                    <form method="POST" action="{{ route('admin.parent-devices.logout', $device) }}" class="d-inline mb-0" onsubmit="return confirm('{{ __('admin.confirm_logout_device') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-warning border-0" title="{{ __('admin.logout_device') }}">
                                            <i class="bi bi-box-arrow-right"></i>
                                        </button>
                                    </form>
                                @endif
                                @php
                                    $isCurrentDevice = isset($currentDeviceToken) && $currentDeviceToken === $device->device_token;
                                    $deleteMessage = $isCurrentDevice 
                                        ? __('admin.confirm_delete_current_device')
                                        : __('admin.confirm_delete_device');
                                @endphp
                                <form method="POST" action="{{ route('admin.parent-devices.destroy', $device) }}" class="d-inline mb-0" onsubmit="return confirm({{ json_encode($deleteMessage) }})">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger border-0" title="{{ __('common.delete') }} {{ __('admin.devices') }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection

