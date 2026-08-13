@extends('layouts.admin')

@section('content')
<x-admin.page-header :title="__('admin.my_children')">
    <x-slot name="controls">
        <div></div>
        <a href="{{ route('admin.children.create') }}" class="btn btn-success">
            <i class="bi bi-plus-circle me-1"></i>
            {{ __('admin.add_child') }}
        </a>
    </x-slot>
</x-admin.page-header>

@if($children->count() === 0)
    <x-ui.toast-notification 
        type="info" 
        :autohide="false"
        icon="bi bi-info-circle"
    >
        {{ __('admin.no_children_yet') }} <a href="{{ route('admin.children.create') }}" class="text-white text-decoration-underline fw-bold">{{ __('admin.add_first_child') }}</a> {{ __('admin.to_get_started') }}
    </x-ui.toast-notification>
@else
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('common.name') }}</th>
                    <th>PIN</th>
                    <th>{{ __('admin.enabled_on_devices') }}</th>
                    <th class="text-end">{{ __('admin.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($children as $child)
                    <tr class="align-middle">
                        <td class="align-middle">
                            <div class="d-flex align-items-center gap-2">
                                @if($child->profile_picture)
                                    <img src="{{ asset('assets/profile-pictures/cats/' . $child->profile_picture) }}" 
                                         alt="{{ $child->username }}" 
                                         class="rounded-circle" 
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                @endif
                                <div>
                                    <span class="fw-bold">{{ $child->username }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            @if($child->hasPin())
                                @php
                                    $pin = $child->getViewPin();
                                @endphp
                                <code class="fs-5 fw-bold text-success">{{ $pin }}</code>
                            @else
                                <span class="text-muted">{{ __('admin.pin_disabled') }}</span>
                            @endif
                        </td>
                        <td class="align-middle">
                            @php
                                $user = auth()->user();
                                $totalDevices = $user->deviceRegistrations()->count();
                                $enabledDevices = \App\Models\DeviceChildVisibility::where('child_user_id', $child->id)
                                    ->whereIn('device_registration_id', $user->deviceRegistrations()->pluck('id'))
                                    ->where('is_visible', true)
                                    ->count();
                                $badgeClass = $enabledDevices > 0 ? 'bg-success' : 'bg-warning';
                            @endphp
                            @if($totalDevices > 0)
                                <span class="badge {{ $badgeClass }}">{{ $enabledDevices }}/{{ $totalDevices }}</span>
                            @else
                                <span class="text-muted small">{{ __('admin.no_devices') }}</span>
                            @endif
                        </td>
                        <td class="align-middle text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                <a href="{{ route('admin.children.edit', $child) }}" class="btn btn-outline-success border-0" title="{{ __('common.edit') }}">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.children.destroy', $child) }}" class="d-inline mb-0" onsubmit="return confirm('{{ __('common.are_you_sure') }}')">
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
@endif
@endsection

