@extends('layouts.admin')

@section('content')
<x-admin.page-header :title="__('admin.viewing_pin')">
    <x-slot name="controls">
        <div></div>
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-success"><i class="bi bi-chevron-left me-1"></i>{{ __('admin.back_to_edit_user') }}</a>
    </x-slot>
</x-admin.page-header>

<div class="card">
    <div class="card-header bg-info">
        <h5 class="mb-0 text-white">
            <i class="bi bi-key me-2"></i>
            {{ __('admin.viewing_pin_for', ['username' => $user->username]) }}
        </h5>
    </div>
    <div class="card-body text-center">
        <p class="text-muted mb-4">{{ __('admin.pin_required_info') }}</p>
        
        <div class="mb-4">
            <label class="form-label text-muted">{{ __('admin.pin_for', ['username' => $user->username]) }}</label>
            <div class="display-1 fw-bold text-success mb-2" style="letter-spacing: 0.5rem;">
                {{ $generatedPin }}
            </div>
            <small class="text-muted">{{ __('admin.share_pin_info') }}</small>
        </div>
        
        <x-ui.toast-notification 
            type="info" 
            :autohide="false"
            icon="bi bi-info-circle"
        >
            {{ __('admin.pin_required_at_url') }} <code class="bg-dark px-2 py-1 rounded">{{ route('pin-entry', ['username' => $user->username]) }}</code>
        </x-ui.toast-notification>
        
        <div class="d-flex flex-column flex-md-row justify-content-center gap-2">
            @if($user->parent_id)
                <a href="{{ route('admin.children.index') }}" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i>
                    {{ __('admin.back_to_my_children') }}
                </a>
            @else
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i>
                    {{ __('admin.back_to_edit_user') }}
                </a>
            @endif
            <form method="POST" action="{{ route('admin.users.pin.regenerate', $user) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning" onclick="return confirm('{{ __('admin.confirm_regenerate_pin') }}')">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    {{ __('admin.regenerate_pin') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

