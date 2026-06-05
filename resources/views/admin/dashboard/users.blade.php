@extends('layouts.admin')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
    <div class="d-flex flex-column gap-2">
        <h2 class="mb-0">{{ __('admin.dashboard') }} - {{ __('admin.users') }}</h2>
        <p class="text-muted mb-0 small">{{ __('admin.select_user_to_view_analytics') }}</p>
    </div>
</div>

@if($parents->isEmpty())
    <div class="alert alert-info" role="alert">
        {{ __('admin.no_users_found') }}
    </div>
@else
    <div class="list-group">
        @foreach($parents as $parent)
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <x-ui.user-avatar 
                            :user="$parent" 
                            variant="tile" 
                            size="small"
                            mb="mb-0"
                        />
                        <div>
                            <h5 class="mb-1">
                                <a href="{{ route('admin.dashboard.user', $parent) }}" class="text-decoration-none">
                                    {{ $parent->username }}
                                </a>
                                @if($parent->role === 'admin')
                                    <span class="badge bg-danger ms-2">{{ __('admin.admin') }}</span>
                                @else
                                    <span class="badge bg-success ms-2">{{ __('admin.parent') }}</span>
                                @endif
                            </h5>
                            @if($parent->children->count() > 0)
                                <small class="text-muted">
                                    {{ __('admin.has_children_count', ['count' => $parent->children->count()]) }}
                                </small>
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('admin.dashboard.user', $parent) }}" class="btn btn-sm btn-success">
                        {{ __('admin.view_analytics') }}
                    </a>
                </div>
                
                @if($parent->children->count() > 0)
                    <div class="mt-3 ps-5">
                        <h6 class="mb-2">{{ __('admin.children') }}:</h6>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($parent->children as $child)
                                <a href="{{ route('admin.dashboard.user', $child) }}" class="btn btn-sm btn-outline-secondary">
                                    <x-ui.user-avatar 
                                        :user="$child" 
                                        variant="tile" 
                                        size="small"
                                        mb="mb-0"
                                    />
                                    <span class="ms-2">{{ $child->username }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
@endsection

