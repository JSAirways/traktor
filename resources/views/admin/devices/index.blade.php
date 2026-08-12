@extends('layouts.admin')

@section('content')
<div class="mb-3">
    <h2 class="mb-3">{{ __('admin.device_management') }}</h2>

    {{-- Filter by Parent --}}
    @if(isset($allParents) && $allParents->count() > 0)
        @php
            $selectedParent = $userFilter
                ? $allParents->firstWhere('id', (int) $userFilter)
                : null;
        @endphp
        <x-ui.user-selector
            :users="$allParents"
            :selected="$selectedParent"
            :route="route('admin.devices.index')"
            param="user_id"
            value-key="id"
            id="devicesUserSelector"
            :all-label="__('admin.all_parents')"
            :aria-label="__('admin.filter_by_parent')"
        />
    @endif
</div>

@if($parents->count() === 0)
    <x-ui.toast-notification 
        type="info" 
        :autohide="false"
        icon="bi bi-info-circle"
        message="{{ __('admin.no_registered_devices') }}"
    />
@else
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('admin.parent') }}</th>
                    <th class="d-none d-md-table-cell">{{ __('admin.email') }}</th>
                    <th>{{ __('admin.devices_count') }}</th>
                    <th class="text-end">{{ __('admin.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($parents as $parent)
                    <tr class="align-middle" data-parent-id="{{ $parent->id }}">
                        <td class="align-middle">
                            <div class="d-flex align-items-center gap-2">
                                @if($parent->deviceRegistrations->count() > 0)
                                    <x-ui.table-accordion-button 
                                        target-id="parent-devices-{{ $parent->id }}"
                                    />
                                @endif
                                <div class="d-flex flex-column">
                                    <span class="fw-bold">{{ $parent->username }}</span>
                                    <span class="text-muted small d-md-none">{{ $parent->email }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle d-none d-md-table-cell">{{ $parent->email }}</td>
                        <td class="align-middle">
                            <span class="badge bg-info">
                                {{ $parent->deviceRegistrations->count() }}
                            </span>
                        </td>
                        <td class="align-middle text-end">
                            @can('admin')
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <a href="{{ route('admin.users.edit', $parent) }}" class="btn btn-outline-success border-0" title="{{ __('common.edit') }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            @endcan
                        </td>
                    </tr>
                    @if($parent->deviceRegistrations->count() > 0)
                        <tr class="no-drag">
                            <!-- Mobile version (colspan 4) -->
                            <td colspan="4" class="p-0 d-md-none">
                                <div class="accordion-collapse collapse" id="parent-devices-{{ $parent->id }}-mobile">
                                    <div class="accordion-body ms-2">
                                        @foreach($parent->deviceRegistrations as $device)
                                            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center py-2 border-bottom gap-2">
                                                <div class="d-flex flex-column flex-grow-1">
                                                    <span class="fw-bold">{{ $device->device_name ?? __('admin.unnamed_device') }}</span>
                                                    <div class="d-flex flex-wrap gap-2 mt-1">
                                                        <small class="text-muted">{{ $device->registered_at->format('Y-m-d H:i') }}</small>
                                                        @if($device->is_active)
                                                            <span class="badge bg-success">{{ __('admin.active') }}</span>
                                                        @else
                                                            <span class="badge bg-secondary">{{ __('admin.inactive') }}</span>
                                                        @endif
                                                        @php
                                                            $visibleCount = $device->childVisibility->where('is_visible', true)->count();
                                                            $totalChildren = $parent->children->count();
                                                        @endphp
                                                        <span class="badge bg-info">{{ $visibleCount }} / {{ $totalChildren }}</span>
                                                    </div>
                                                    <div class="mt-2">
                                                        @include('admin.devices.partials.capability-badges', ['capabilities' => $device->capabilities])
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <a href="{{ route('admin.devices.show', $device) }}" class="btn btn-outline-success border-0" title="{{ __('admin.manage') }}">
                                                        <i class="bi bi-gear"></i>
                                                    </a>
                                                    @if($device->is_active)
                                                        <form method="POST" action="{{ route('admin.devices.deactivate', $device) }}" class="d-inline mb-0">
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-warning border-0" title="{{ __('admin.deactivate') }}" onclick="return confirm('{{ __('admin.confirm_deactivate_device') }}')">
                                                                <i class="bi bi-pause-circle"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <form method="POST" action="{{ route('admin.devices.activate', $device) }}" class="d-inline mb-0">
                                                            @csrf
                                                            <button type="submit" class="btn btn-outline-success border-0" title="{{ __('admin.activate') }}">
                                                                <i class="bi bi-play-circle"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @php
                                                        $isCurrentDevice = isset($currentDeviceToken) && $currentDeviceToken === $device->device_token;
                                                        $deleteMessage = $isCurrentDevice 
                                                            ? __('admin.confirm_delete_current_device')
                                                            : __('admin.confirm_delete_device');
                                                    @endphp
                                                    <form method="POST" action="{{ route('admin.devices.destroy', $device) }}" class="d-inline mb-0" onsubmit="return confirm({{ json_encode($deleteMessage) }})">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger border-0" title="{{ __('common.delete') }}">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                            <!-- Desktop version (colspan 4) -->
                            <td colspan="4" class="p-0 d-none d-md-table-cell">
                                <div class="accordion-collapse collapse" id="parent-devices-{{ $parent->id }}">
                                    <div class="accordion-body ps-4 pe-1">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('admin.device_name_label') }}</th>
                                                    <th>{{ __('admin.registered') }}</th>
                                                    <th>{{ __('admin.last_used') }}</th>
                                                    <th>{{ __('admin.status') }}</th>
                                                    <th>{{ __('admin.visible_children') }}</th>
                                                    <th>{{ __('admin.capability_summary') }}</th>
                                                    <th class="text-end">{{ __('admin.actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($parent->deviceRegistrations as $device)
                                                    <tr>
                                                        <td>{{ $device->device_name ?? __('admin.unnamed_device') }}</td>
                                                        <td><small>{{ $device->registered_at->format('Y-m-d H:i') }}</small></td>
                                                        <td><small>{{ $device->last_used_at ? $device->last_used_at->format('Y-m-d H:i') : __('admin.never') }}</small></td>
                                                        <td>
                                                            @if($device->is_active)
                                                                <span class="badge bg-success">{{ __('admin.active') }}</span>
                                                            @else
                                                                <span class="badge bg-secondary">{{ __('admin.inactive') }}</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @php
                                                                $visibleCount = $device->childVisibility->where('is_visible', true)->count();
                                                                $totalChildren = $parent->children->count();
                                                            @endphp
                                                            <span class="badge bg-info">
                                                                {{ $visibleCount }} / {{ $totalChildren }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @include('admin.devices.partials.capability-badges', ['capabilities' => $device->capabilities])
                                                        </td>
                                                        <td class="text-end">
                                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                                <a href="{{ route('admin.devices.show', $device) }}" class="btn btn-outline-success border-0" title="{{ __('admin.manage') }}">
                                                                    <i class="bi bi-gear"></i>
                                                                </a>
                                                                @if($device->is_active)
                                                                    <form method="POST" action="{{ route('admin.devices.deactivate', $device) }}" class="d-inline mb-0">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-outline-warning border-0" title="{{ __('admin.deactivate') }}" onclick="return confirm('{{ __('admin.confirm_deactivate_device') }}')">
                                                                            <i class="bi bi-pause-circle"></i>
                                                                        </button>
                                                                    </form>
                                                                @else
                                                                    <form method="POST" action="{{ route('admin.devices.activate', $device) }}" class="d-inline mb-0">
                                                                        @csrf
                                                                        <button type="submit" class="btn btn-outline-success border-0" title="{{ __('admin.activate') }}">
                                                                            <i class="bi bi-play-circle"></i>
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                                @php
                                                                    $isCurrentDevice = isset($currentDeviceToken) && $currentDeviceToken === $device->device_token;
                                                                    $deleteMessage = $isCurrentDevice 
                                                                        ? __('admin.confirm_delete_current_device')
                                                                        : __('admin.confirm_delete_device');
                                                                @endphp
                                                                <form method="POST" action="{{ route('admin.devices.destroy', $device) }}" class="d-inline mb-0" onsubmit="return confirm({{ json_encode($deleteMessage) }})">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-outline-danger border-0" title="{{ __('common.delete') }}">
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
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $parents->links() }}
@endif
@endsection

