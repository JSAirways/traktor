@extends('layouts.admin')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
    <h2 class="mb-0">{{ __('admin.create_user') }}</h2>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline-success"><i class="bi bi-chevron-left me-1"></i>{{ __('common.back') }}</a>
</div>

<form method="POST" action="{{ route('admin.users.store') }}">
    @csrf
    
    {{-- Email field (required for parents, hidden for children) --}}
    <div class="form-floating mb-3" id="emailField">
        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder=" " required>
        <label for="email">{{ __('admin.email_label') }} *</label>
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    
    {{-- Hidden email field for children (set to empty) --}}
    <input type="hidden" name="email" id="emailHidden" value="" style="display: none;">
    
    {{-- Name field (required for children, optional for parents) --}}
    <div class="form-floating mb-3" id="nameField" style="display: none;">
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder=" ">
        <label for="name">{{ __('admin.name_label') }} *</label>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">{{ __('admin.child_name_help') }}</div>
    </div>
    
    <div class="form-floating mb-3">
        <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username') }}" placeholder=" " required>
        <label for="username">{{ __('admin.username_label') }} *</label>
        @error('username')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    
    <div class="form-floating mb-3">
        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder=" " required>
        <label for="password">{{ __('admin.password_label') }} *</label>
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    
    <div class="form-floating mb-3">
        <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required onchange="toggleParentSelector()">
            <option value="user" {{ old('role') === 'user' ? 'selected' : '' }}>{{ __('admin.user') }}</option>
            <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>{{ __('admin.admin') }}</option>
        </select>
        <label for="role">{{ __('admin.role_label') }} *</label>
        @error('role')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    
    {{-- Parent Selection (for child accounts) --}}
    <div class="mb-3" id="parentSelector" style="display: none;">
        <div class="form-floating">
            <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                <option value="">{{ __('admin.none_regular_user') }}</option>
                @php
                    $potentialParents = \App\Models\User::whereNull('parent_id')
                        ->where('role', 'user')
                        ->orderBy('username')
                        ->get();
                @endphp
                @foreach($potentialParents as $parent)
                    <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
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
    
    {{-- Account Status (for admin-created users) --}}
    <div class="form-floating mb-3">
        <select class="form-select @error('account_status') is-invalid @enderror" id="account_status" name="account_status">
            <option value="approved" {{ old('account_status', 'approved') === 'approved' ? 'selected' : '' }}>{{ __('admin.approved') }}</option>
            <option value="pending" {{ old('account_status') === 'pending' ? 'selected' : '' }}>{{ __('admin.pending_approval') }}</option>
            <option value="rejected" {{ old('account_status') === 'rejected' ? 'selected' : '' }}>{{ __('admin.rejected') }}</option>
        </select>
        <label for="account_status">{{ __('admin.account_status_label') }}</label>
        @error('account_status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">{{ __('admin.account_status_label') }}</div>
    </div>
    
    <x-forms.profile-picture-selector 
        name="cat_gif"
        :currentValue="old('cat_gif', '')"
        :pictures="$catGifs"
        category="cats"
    />
    
    <script>
        function toggleParentSelector() {
            const roleSelect = document.getElementById('role');
            const parentSelector = document.getElementById('parentSelector');
            const parentIdSelect = document.getElementById('parent_id');
            const emailField = document.getElementById('emailField');
            const emailHidden = document.getElementById('emailHidden');
            const emailInput = document.getElementById('email');
            const nameField = document.getElementById('nameField');
            const nameInput = document.getElementById('name');
            
            if (roleSelect.value === 'user') {
                parentSelector.style.display = 'block';
                
                // Check if parent is selected
                const parentId = parentIdSelect ? parentIdSelect.value : '';
                if (parentId) {
                    // Child account: hide email, show name
                    emailField.style.display = 'none';
                    emailHidden.style.display = 'block';
                    emailInput.removeAttribute('required');
                    nameField.style.display = 'block';
                    nameInput.setAttribute('required', 'required');
                } else {
                    // Parent account: show email, hide name
                    emailField.style.display = 'block';
                    emailHidden.style.display = 'none';
                    emailInput.setAttribute('required', 'required');
                    nameField.style.display = 'none';
                    nameInput.removeAttribute('required');
                }
            } else {
                parentSelector.style.display = 'none';
                if (parentIdSelect) parentIdSelect.value = '';
                // Admin account: show email, hide name
                emailField.style.display = 'block';
                emailHidden.style.display = 'none';
                emailInput.setAttribute('required', 'required');
                nameField.style.display = 'none';
                nameInput.removeAttribute('required');
            }
        }
        
        // Handle parent selection change
        function handleParentChange() {
            toggleParentSelector();
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleParentSelector();
            
            // Add listener for parent_id changes
            const parentIdSelect = document.getElementById('parent_id');
            if (parentIdSelect) {
                parentIdSelect.addEventListener('change', handleParentChange);
            }
        });
    </script>
    
    <button type="submit" class="btn btn-success w-100 w-md-auto">{{ __('admin.create_user') }}</button>
</form>
@endsection

