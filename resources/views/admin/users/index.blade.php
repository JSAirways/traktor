@extends('layouts.admin')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
    <div class="d-flex flex-column gap-2">
        <h2 class="mb-0">{{ __('admin.users') }}</h2>
        @if($pendingCount > 0)
            <a href="{{ route('admin.users.pending') }}" class="btn btn-warning btn-sm">
                <i class="bi bi-hourglass-split me-1"></i>
                {{ $pendingCount }} {{ $pendingCount > 1 ? __('admin.pending_registrations_plural') : __('admin.pending_registration') }}
            </a>
        @endif
    </div>
    <div class="d-flex gap-2">
        @can('admin')
            <a href="{{ route('admin.users.create') }}" class="btn btn-success">{{ __('admin.add_user') }}</a>
        @endcan
    </div>
</div>

{{-- Status Filter --}}
<div class="mb-3">
    <div class="btn-group" role="group">
        <a href="{{ route('admin.users.index', ['status' => 'all']) }}" 
           class="btn btn-secondary {{ ($status ?? 'all') === 'all' ? 'active' : '' }}">
            {{ __('admin.all') }}
        </a>
        <a href="{{ route('admin.users.index', ['status' => 'pending']) }}" 
           class="btn btn-warning {{ ($status ?? 'all') === 'pending' ? 'active' : '' }}">
            {{ __('admin.pending_approval') }}
        </a>
        <a href="{{ route('admin.users.index', ['status' => 'approved']) }}" 
           class="btn btn-success {{ ($status ?? 'all') === 'approved' ? 'active' : '' }}">
            {{ __('admin.approved') }}
        </a>
        <a href="{{ route('admin.users.index', ['status' => 'rejected']) }}" 
           class="btn btn-danger {{ ($status ?? 'all') === 'rejected' ? 'active' : '' }}">
            {{ __('admin.rejected') }}
        </a>
    </div>
</div>

<div class="table-responsive">
<table class="table table-striped">
    <thead>
        <tr>
            <th style="width: 25%;">{{ __('common.username') }}</th>
            <th style="width: 18%;">{{ __('common.email') }}</th>
            <th style="width: 10%;">{{ __('admin.children') }}</th>
            <th style="width: 12%;">{{ __('admin.status') }}</th>
            <th style="width: 12%;">PIN</th>
            <th style="width: 23%;" class="text-end">{{ __('admin.actions') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($users as $user)
            <tr class="align-middle" data-user-id="{{ $user->id }}">
                <td class="align-middle">
                    <div class="d-flex align-items-center gap-2">
                        @if($user->children->count() > 0)
                            <x-ui.table-accordion-button 
                                target-id="user-children-{{ $user->id }}"
                            />
                        @endif
                        <div class="d-flex align-items-center gap-2">
                            @if($user->is_random_profile_picture)
                                <div class="rounded-circle bg-dark border border-success d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <i class="bi bi-shuffle text-success"></i>
                                </div>
                            @elseif($user->profile_picture_path)
                                <img src="{{ $user->profile_picture_path }}" 
                                     alt="{{ $user->username }}" 
                                     class="rounded-circle" 
                                     style="width: 40px; height: 40px; object-fit: cover;">
                            @endif
                            <div>
                                <span class="fw-bold">{{ $user->username }}</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="align-middle">
                    <span class="text-muted">{{ $user->email ?? '-' }}</span>
                </td>
                <td class="align-middle">
                    <span class="badge bg-info">
                        {{ $user->children->count() }}
                    </span>
                </td>
                <td class="align-middle">
                    @if($user->account_status)
                        <span class="badge bg-{{ $user->status_color ?? 'secondary' }}">
                            {{ ucfirst($user->account_status) }}
                        </span>
                    @endif
                </td>
                <td class="align-middle">
                    @if($user->parent_id === null)
                        @if($user->has_pin ?? $user->hasPin())
                            <div class="d-flex align-items-center gap-2">
                                <code class="fs-5 fw-bold text-success pin-display" data-pin="{{ $user->pin_value ?? $user->getViewPin() }}" data-user-id="{{ $user->id }}">
                                    <span class="pin-hidden">••••</span>
                                    <span class="pin-visible d-none">{{ $user->pin_value ?? $user->getViewPin() }}</span>
                                </code>
                                <button type="button" class="btn btn-outline-success border-0 px-1 py-0 pin-toggle-btn" data-user-id="{{ $user->id }}" title="{{ __('admin.reveal_pin') }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        @else
                            <span class="text-muted small">{{ __('admin.pin_disabled') }}</span>
                        @endif
                    @else
                        <span class="text-muted small">-</span>
                    @endif
                </td>
                <td class="align-middle text-end">
                    @can('update', $user)
                        <div class="d-flex align-items-center justify-content-end gap-2">
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-outline-success border-0" title="{{ __('common.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @can('delete', $user)
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline mb-0" onsubmit="return confirm('{{ __('common.are_you_sure') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger border-0" title="{{ __('common.delete') }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    @endcan
                </td>
            </tr>
            @if($user->children->count() > 0)
                <tr class="no-drag">
                    <!-- Mobile version (colspan 6) -->
                    <td colspan="6" class="p-0 d-md-none">
                        <div class="accordion-collapse collapse" id="user-children-{{ $user->id }}-mobile">
                            <div class="accordion-body ms-2">
                                @foreach($user->children as $child)
                                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center py-2 border-bottom gap-2">
                                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                                            @php
                                                $childProfilePicturePath = null;
                                                $childIsRandom = false;
                                                if ($child->profile_picture) {
                                                    $category = $child->profile_picture_category ?? 'cats';
                                                    $childProfilePicturePath = asset('assets/profile-pictures/' . $category . '/' . $child->profile_picture);
                                                } elseif ($child->cat_gif) {
                                                    $childProfilePicturePath = asset('assets/profile-pictures/cats/' . $child->cat_gif);
                                                } else {
                                                    $childIsRandom = true;
                                                }
                                            @endphp
                                            @if($childIsRandom)
                                                <div class="rounded-circle bg-dark border border-success d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-shuffle text-success"></i>
                                                </div>
                                            @elseif($childProfilePicturePath)
                                                <img src="{{ $childProfilePicturePath }}" 
                                                     alt="{{ $child->username }}" 
                                                     class="rounded-circle flex-shrink-0" 
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                            @endif
                                            <div class="d-flex flex-column flex-grow-1">
                                                <span class="fw-bold">{{ $child->username }}</span>
                                                <div class="d-flex flex-wrap gap-2 mt-1 align-items-center">
                                                    @php
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
                                                    @if($child->hasPin())
                                                        @php
                                                            $pin = $child->getViewPin();
                                                        @endphp
                                                        <div class="d-flex align-items-center gap-1">
                                                            <code class="fs-6 fw-bold text-success pin-display" data-pin="{{ $pin }}" data-user-id="{{ $child->id }}">
                                                                <span class="pin-hidden">••••</span>
                                                                <span class="pin-visible d-none">{{ $pin }}</span>
                                                            </code>
                                                            <button type="button" class="btn btn-outline-success border-0 px-1 py-0 pin-toggle-btn" data-user-id="{{ $child->id }}" title="{{ __('admin.reveal_pin') }}">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        </div>
                                                    @else
                                                        <span class="text-muted small">No PIN</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            @can('update', $child)
                                                <a href="{{ route('admin.users.edit', $child) }}" class="btn btn-outline-success border-0" title="{{ __('common.edit') }}">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                @can('delete', $child)
                                                    <form method="POST" action="{{ route('admin.users.destroy', $child) }}" class="d-inline mb-0" onsubmit="return confirm('{{ __('common.are_you_sure') }}')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger border-0" title="{{ __('common.delete') }}">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endcan
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </td>
                    <!-- Desktop version (colspan 6) -->
                    <td colspan="6" class="p-0 d-none d-md-table-cell">
                        <div class="accordion-collapse collapse" id="user-children-{{ $user->id }}">
                            <div class="accordion-body ps-4 pe-1">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 39%;">{{ __('common.name') }}</th>
                                            <th style="width: 15%;">{{ __('admin.enabled_on_devices') }}</th>
                                            <th style="width: 15%;">PIN</th>
                                            <th style="width: 30%;" class="text-end">{{ __('admin.actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($user->children as $child)
                                            <tr class="align-middle">
                                                <td class="align-middle">
                                                    <div class="d-flex align-items-center gap-2">
                                                        @php
                                                            $childProfilePicturePath = null;
                                                            $childIsRandom = false;
                                                            if ($child->profile_picture) {
                                                                $category = $child->profile_picture_category ?? 'cats';
                                                                $childProfilePicturePath = asset('assets/profile-pictures/' . $category . '/' . $child->profile_picture);
                                                            } elseif ($child->cat_gif) {
                                                                $childProfilePicturePath = asset('assets/profile-pictures/cats/' . $child->cat_gif);
                                                            } else {
                                                                $childIsRandom = true;
                                                            }
                                                        @endphp
                                                        @if($childIsRandom)
                                                            <div class="rounded-circle bg-dark border border-success d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                <i class="bi bi-shuffle text-success"></i>
                                                            </div>
                                                        @elseif($childProfilePicturePath)
                                                            <img src="{{ $childProfilePicturePath }}" 
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
                                                    @php
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
                                                <td class="align-middle">
                                                    @if($child->hasPin())
                                                        @php
                                                            $pin = $child->getViewPin();
                                                        @endphp
                                                        <div class="d-flex align-items-center gap-2">
                                                            <code class="fs-5 fw-bold text-success pin-display" data-pin="{{ $pin }}" data-user-id="{{ $child->id }}">
                                                                <span class="pin-hidden">••••</span>
                                                                <span class="pin-visible d-none">{{ $pin }}</span>
                                                            </code>
                                                            <button type="button" class="btn btn-outline-success border-0 px-1 py-0 pin-toggle-btn" data-user-id="{{ $child->id }}" title="{{ __('admin.reveal_pin') }}">
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        </div>
                                                    @else
                                                        <span class="text-muted">{{ __('admin.pin_disabled') }}</span>
                                                    @endif
                                                </td>
                                                <td class="align-middle text-end">
                                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                                        @can('update', $child)
                                                            <a href="{{ route('admin.users.edit', $child) }}" class="btn btn-outline-success border-0" title="{{ __('common.edit') }}">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            @can('delete', $child)
                                                                <form method="POST" action="{{ route('admin.users.destroy', $child) }}" class="d-inline mb-0" onsubmit="return confirm('{{ __('common.are_you_sure') }}')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-outline-danger border-0" title="{{ __('common.delete') }}">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                </form>
                                                            @endcan
                                                        @endcan
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

{{ $users->links() }}

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle PIN reveal/hide toggle
    const pinToggleButtons = document.querySelectorAll('.pin-toggle-btn');
    
    pinToggleButtons.forEach(button => {
        const userId = button.getAttribute('data-user-id');
        // Find the pin display - try in same flex container first (most specific), then row, then document
        const flexContainer = button.closest('.d-flex');
        const row = button.closest('tr');
        const searchContainer = flexContainer || row || document;
        const pinDisplay = searchContainer.querySelector(`.pin-display[data-user-id="${userId}"]`);
        const pinHidden = pinDisplay?.querySelector('.pin-hidden');
        const pinVisible = pinDisplay?.querySelector('.pin-visible');
        const eyeIcon = button.querySelector('i');
        
        if (!pinDisplay || !pinHidden || !pinVisible || !eyeIcon) return;
        
        let isRevealed = false;
        
        function revealPin() {
            if (isRevealed) return;
            isRevealed = true;
            pinHidden.classList.add('d-none');
            pinVisible.classList.remove('d-none');
            eyeIcon.classList.remove('bi-eye');
            eyeIcon.classList.add('bi-eye-slash');
            button.setAttribute('title', '{{ __('admin.hide_pin') }}');
        }
        
        function hidePin() {
            if (!isRevealed) return;
            isRevealed = false;
            pinHidden.classList.remove('d-none');
            pinVisible.classList.add('d-none');
            eyeIcon.classList.remove('bi-eye-slash');
            eyeIcon.classList.add('bi-eye');
            button.setAttribute('title', '{{ __('admin.reveal_pin') }}');
        }
        
        // Mouse events for click and hold
        button.addEventListener('mousedown', function(e) {
            e.preventDefault();
            revealPin();
        });
        
        button.addEventListener('mouseup', function(e) {
            e.preventDefault();
            hidePin();
        });
        
        button.addEventListener('mouseleave', function(e) {
            e.preventDefault();
            hidePin();
        });
        
        // Touch events for mobile
        button.addEventListener('touchstart', function(e) {
            e.preventDefault();
            revealPin();
        });
        
        button.addEventListener('touchend', function(e) {
            e.preventDefault();
            hidePin();
        });
        
        button.addEventListener('touchcancel', function(e) {
            e.preventDefault();
            hidePin();
        });
    });
});
</script>
@endsection

