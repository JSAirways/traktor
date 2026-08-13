@extends('layouts.admin')

@section('content')
<x-admin.page-header :title="__('admin.dashboard') . ' - ' . $user->username">
    <x-slot name="subtitle">
        <div class="d-flex align-items-center gap-2">
            <x-ui.user-avatar
                :user="$user"
                variant="tile"
                size="small"
                mb="mb-0"
            />
            <div>
                @if($user->role === 'admin')
                    <span class="badge bg-danger">{{ __('admin.admin') }}</span>
                @elseif($user->parent_id)
                    <span class="badge bg-success">{{ __('admin.child') }}</span>
                @else
                    <span class="badge bg-success">{{ __('admin.parent') }}</span>
                @endif
            </div>
        </div>
    </x-slot>
    <x-slot name="controls">
        <div></div>
        <a href="{{ route('admin.dashboard.users') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>
            {{ __('admin.back_to_users') }}
        </a>
    </x-slot>
</x-admin.page-header>

@include('admin.dashboard._panel', [
    'displayUser' => $displayUser,
    'availableUsers' => $availableUsers,
    'showTitle' => false,
    'showUserSelector' => false,
])

@if($children->count() > 0)
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">{{ __('admin.children_analytics') }}</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($children as $child)
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <x-ui.user-avatar
                                        :user="$child"
                                        variant="tile"
                                        size="small"
                                        mb="mb-0"
                                    />
                                    <div>
                                        <h6 class="mb-0">{{ $child->username }}</h6>
                                        <small class="text-muted">{{ __('admin.child') }}</small>
                                    </div>
                                </div>
                                <a href="{{ route('admin.dashboard.user', $child) }}" class="btn btn-sm btn-success w-100">
                                    {{ __('admin.view_analytics') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif

@include('admin.dashboard._scripts')
@endsection
