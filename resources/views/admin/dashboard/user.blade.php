@extends('layouts.admin')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
    <div class="d-flex flex-column gap-2">
        <h2 class="mb-0">{{ __('admin.dashboard') }} - {{ $user->username }}</h2>
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
    </div>
    <a href="{{ route('admin.dashboard.users') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>
        {{ __('admin.back_to_users') }}
    </a>
</div>

@if($user->parent_id && $children->isEmpty())
    <!-- Child user dashboard -->
    @include('admin.dashboard.index', [
        'user' => $authUser,
        'displayUser' => $user,
        'availableUsers' => collect([$user])->map(fn($u) => ['id' => $u->id, 'slug' => $u->slug, 'username' => $u->username, 'role' => $u->role, 'parent_id' => $u->parent_id])->toArray()
    ])
@else
    <!-- Parent user dashboard with children -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">{{ $user->username }}'s Analytics</h5>
        </div>
        <div class="card-body">
            @include('admin.dashboard.index', [
                'user' => $authUser,
                'displayUser' => $user,
                'availableUsers' => collect([$user])->map(fn($u) => ['id' => $u->id, 'slug' => $u->slug, 'username' => $u->username, 'role' => $u->role, 'parent_id' => $u->parent_id])->toArray()
            ])
        </div>
    </div>
    
    @if($children->count() > 0)
        <div class="card">
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
@endif
@endsection

