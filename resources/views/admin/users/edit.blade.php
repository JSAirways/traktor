@extends('layouts.admin')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
    <h2 class="mb-0">{{ isset($isSelfEdit) && $isSelfEdit ? __('admin.edit_my_profile') : __('admin.edit_user') }}</h2>
    @if(isset($isSelfEdit) && $isSelfEdit)
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-success"><i class="bi bi-chevron-left me-1"></i>{{ __('common.back') }}</a>
    @else
        <a href="{{ route('admin.users.index') }}" class="btn btn-outline-success"><i class="bi bi-chevron-left me-1"></i>{{ __('common.back') }}</a>
    @endif
</div>

<form method="POST" action="{{ isset($isSelfEdit) && $isSelfEdit ? route('admin.profile.update') : route('admin.users.update', $user) }}">
    @csrf
    @method('PUT')
    
    @if($user->parent_id === null)
        {{-- Email field for parent accounts (required) --}}
        <div class="form-floating mb-3">
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" placeholder=" " required>
            <label for="email">{{ __('admin.email_label') }} *</label>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @else
        {{-- Email field for child accounts (hidden, not editable) --}}
        <input type="hidden" name="email" value="">
        <div class="alert alert-info mb-3">
            <small><i class="bi bi-info-circle me-1"></i>{{ __('admin.child_accounts_no_email') }}</small>
        </div>
    @endif
    
    <div class="form-floating mb-3">
        <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $user->username) }}" placeholder=" " required>
        <label for="username">{{ __('admin.username_label') }} *</label>
        @error('username')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    
    <div class="form-floating mb-3">
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder=" ">
        <label for="password">{{ __('admin.password_leave_blank') }}</label>
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    
    @if(isset($isSelfEdit) && $isSelfEdit)
        {{-- Profile Selection Toggle and PIN Management (only for parents) --}}
        @if($user->parent_id === null)
            <div class="row mb-3">
                
                {{-- PIN Field --}}
                <x-forms.pin-field 
                    :user="$user" 
                    :currentPin="$currentPin ?? null"
                    columnClasses="col-12 col-md-6 mb-3 mb-md-0"
                />

                {{-- Hide from Profile Selection Toggle --}}
                <div class="col-12 col-md-6">
                    <div class="form-check form-switch">
                        @php
                            // Inverted logic: checkbox checked = hide (false), unchecked = show (true)
                            // If old value exists (from validation error), it's the checkbox state (1 or not present)
                            // Otherwise, use the inverse of the current appears_in_profile_selection value
                            $shouldBeChecked = old('appears_in_profile_selection') !== null 
                                ? old('appears_in_profile_selection') == '1' 
                                : !$user->appears_in_profile_selection;
                        @endphp
                        <input class="form-check-input" type="checkbox" id="appears_in_profile_selection" name="appears_in_profile_selection" value="1" {{ $shouldBeChecked ? 'checked' : '' }}>
                        <label class="form-check-label fw-bold" for="appears_in_profile_selection">
                            {{ __('admin.hide_from_profile_selection') }}
                        </label>
                    </div>
                    <div class="form-text">{{ __('admin.hide_from_profile_selection_help') }}</div>
                </div>
            </div>
            
            <script type="application/json" data-pin-field>
            {
                "pinWrapperId": "pin-field-wrapper",
                "pinInputId": "pin",
                "pinAsteriskId": "pin-asterisk",
                "usePinCheckboxId": "use_pin",
                "currentPin": "{{ ($currentPin ?? $user->getViewPin()) ?? '' }}"
            }
            </script>
        @endif
        {{-- Users cannot change their own role - hide if not admin --}}
        @if(auth()->user()->isAdmin())
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="role" value="{{ ucfirst($user->role) }}" placeholder=" " disabled>
                <label for="role">{{ __('admin.role_label') }}</label>
                <div class="form-text">{{ __('admin.cannot_change_own_role') }}</div>
                <input type="hidden" name="role" value="{{ $user->role }}">
            </div>
        @endif
    @else
        {{-- Admins can change other users' roles --}}
        <div class="form-floating mb-3">
            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required onchange="toggleParentSelector()">
                    <option value="user" {{ old('role', $user->role) === 'user' ? 'selected' : '' }}>{{ __('admin.user') }}</option>
                    <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>{{ __('admin.admin') }}</option>
            </select>
            <label for="role">{{ __('admin.role_label') }} *</label>
            @error('role')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        
        {{-- Parent Selection (for child accounts) - Admin only --}}
        <div class="mb-3" id="parentSelector" style="display: {{ old('role', $user->role) === 'user' ? 'block' : 'none' }};">
            <div class="form-floating">
                <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                    <option value="">{{ __('admin.none_regular_user') }}</option>
                    @php
                        $potentialParents = \App\Models\User::whereNull('parent_id')
                            ->where('role', 'user')
                            ->where('id', '!=', $user->id)
                            ->orderBy('username')
                            ->get();
                    @endphp
                    @foreach($potentialParents as $parent)
                        <option value="{{ $parent->id }}" {{ old('parent_id', $user->parent_id) == $parent->id ? 'selected' : '' }}>
                            {{ $parent->name }}
                        </option>
                    @endforeach
                </select>
                <label for="parent_id">{{ __('admin.parent_account_label') }}</label>
                @error('parent_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-text">{{ __('admin.parent_account_help') }}</div>
        </div>
        
        {{-- Account Status - Admin only --}}
        <div class="form-floating mb-3">
            <select class="form-select @error('account_status') is-invalid @enderror" id="account_status" name="account_status">
                <option value="pending" {{ old('account_status', $user->account_status) === 'pending' ? 'selected' : '' }}>{{ __('admin.pending_approval') }}</option>
                <option value="approved" {{ old('account_status', $user->account_status) === 'approved' ? 'selected' : '' }}>{{ __('admin.approved') }}</option>
                <option value="rejected" {{ old('account_status', $user->account_status) === 'rejected' ? 'selected' : '' }}>{{ __('admin.rejected') }}</option>
            </select>
            <label for="account_status">{{ __('admin.account_status_label') }}</label>
            @error('account_status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        
        {{-- Is Viewable - Admin only --}}
        <div class="mb-3">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_viewable" name="is_viewable" value="1" {{ old('is_viewable', $user->is_viewable) ? 'checked' : '' }}>
                <label class="form-check-label fw-bold" for="is_viewable">
                    {{ __('admin.viewing_page_enabled') }}
                </label>
            </div>
            <div class="form-text">{{ __('admin.viewing_page_enabled_help') }}</div>
        </div>
        
        {{-- PIN Management for Parent Accounts - Admin only --}}
        @if($user->parent_id === null)
            <div class="row mb-3">
                {{-- PIN Field --}}
                <x-forms.pin-field 
                    :user="$user" 
                    :currentPin="$currentPin ?? null"
                    columnClasses="col-12 col-md-6 mb-3 mb-md-0"
                />
            </div>
            
            <script type="application/json" data-pin-field>
            {
                "pinWrapperId": "pin-field-wrapper",
                "pinInputId": "pin",
                "pinAsteriskId": "pin-asterisk",
                "usePinCheckboxId": "use_pin",
                "currentPin": "{{ ($currentPin ?? $user->getViewPin()) ?? '' }}"
            }
            </script>
        @endif
    @endif
    
    @php
        // Get current profile picture - use profile_picture if available, otherwise fallback to cat_gif for backward compatibility
        $currentPicture = $user->profile_picture ?? ($user->cat_gif ?? '');
        // Only show if category is 'cats' (legacy cat gifs)
        if ($user->profile_picture_category && $user->profile_picture_category !== 'cats') {
            $currentPicture = '';
        }
    @endphp
    <x-forms.profile-picture-selector 
        name="cat_gif"
        :currentValue="$currentPicture"
        :pictures="$catGifs"
        category="cats"
    />
    
    <script>
        function toggleParentSelector() {
            const roleSelect = document.getElementById('role');
            const parentSelector = document.getElementById('parentSelector');
            if (roleSelect && parentSelector) {
                if (roleSelect.value === 'user') {
                    parentSelector.style.display = 'block';
                } else {
                    parentSelector.style.display = 'none';
                    const parentIdSelect = document.getElementById('parent_id');
                    if (parentIdSelect) {
                        parentIdSelect.value = '';
                    }
                }
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleParentSelector();
        });
    </script>
    
    <button type="submit" class="btn btn-success w-100 w-md-auto">{{ isset($isSelfEdit) && $isSelfEdit ? __('admin.update_profile') : __('admin.update_user') }}</button>
</form>

@can('admin')
    @if($user->parent_id !== null && !isset($isSelfEdit))
        {{-- Convert to Parent Account Section --}}
        <div class="card mt-4 border-warning">
            <div class="card-header bg-warning bg-opacity-10">
                <h5 class="mb-0"><i class="bi bi-arrow-repeat me-2"></i>{{ __('admin.convert_to_parent_account') }}</h5>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">{{ __('admin.convert_to_parent_description') }}</p>
                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#convertToParentModal">
                    <i class="bi bi-arrow-repeat me-1"></i>{{ __('admin.convert_to_parent') }}
                </button>
            </div>
        </div>

        {{-- Convert to Parent Modal --}}
        <div class="modal fade" id="convertToParentModal" tabindex="-1" aria-labelledby="convertToParentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="convertToParentModalLabel">{{ __('admin.convert_to_parent_account') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('admin.users.convert-to-parent', $user) }}">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>{{ __('admin.convert_to_parent_warning') }}
                            </div>
                            
                            @if($errors->any())
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="convert_email" name="email" value="{{ old('email', '') }}" placeholder=" " required>
                                <label for="convert_email">{{ __('admin.email_label') }} *</label>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="convert_password" name="password" placeholder=" " required>
                                <label for="convert_password">{{ __('admin.password_label') }} *</label>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">{{ __('admin.convert_password_help') }}</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="convert_password_confirmation" name="password_confirmation" placeholder=" " required>
                                <label for="convert_password_confirmation">{{ __('admin.password_confirmation_label') }} *</label>
                                @error('password_confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                            <button type="submit" class="btn btn-warning">{{ __('admin.convert_to_parent') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endcan
@endsection

