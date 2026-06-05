@extends('layouts.admin')

@push('styles')
    @if($googleCloudProjectId?->value && $googleCloudServiceAccount?->value)
        @vite(['resources/js/admin/settings/quota-monitor.js'])
    @endif
@endpush

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4">
    <h2 class="mb-0">{{ __('admin.settings') }}</h2>
</div>

{{-- YouTube API Quota Monitoring Card --}}
@if($googleCloudProjectId?->value && $googleCloudServiceAccount?->value)
<div class="card mb-4" id="quotaCard">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('admin.youtube_quota_title') }}</h5>
        <button type="button" class="btn btn-sm btn-outline-success" id="refreshQuotaBtn" 
                data-refresh-text="{{ __('admin.youtube_quota_refresh') }}" 
                data-refreshing-text="{{ __('admin.youtube_quota_refreshing') }}">
            <i class="bi bi-arrow-clockwise"></i> <span id="refreshBtnText">{{ __('admin.youtube_quota_refresh') }}</span>
        </button>
    </div>
    <div class="card-body">
        <div id="quotaLoading" class="text-center py-3 d-none">
            <div class="spinner-border text-success" role="status">
                <span class="visually-hidden">{{ __('common.loading') }}</span>
            </div>
        </div>
        
        <div id="quotaError" class="alert alert-warning d-none" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <span id="quotaErrorMessage"></span>
        </div>
        
        <div id="quotaContent">
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><strong id="quotaUsed">-</strong> / <strong id="quotaLimit">-</strong> {{ __('admin.youtube_quota_units') }}</span>
                    <span class="text-muted"><strong id="quotaPercentage">-</strong>% {{ __('admin.youtube_quota_of_daily_quota') }}</span>
                </div>
                <div class="progress" style="height: 25px;">
                    <div id="quotaProgressBar" class="progress-bar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                        <span id="quotaProgressText">0%</span>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>{{ __('admin.youtube_quota_remaining') }}:</strong> <span id="quotaRemaining">-</span> {{ __('admin.youtube_quota_units') }}</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1 text-muted"><small>{{ __('admin.youtube_quota_last_updated') }}: <span id="quotaTimestamp">{{ __('admin.youtube_quota_never_updated') }}</span></small></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<form method="POST" action="{{ route('admin.settings.update') }}">
    @csrf
    @method('PUT')
    
    <div class="mb-3">
        <label for="youtube_api_key" class="form-label">{{ __('admin.youtube_api_key') }} <span class="text-danger">*</span></label>
        <textarea class="form-control @error('youtube_api_key') is-invalid @enderror" id="youtube_api_key" name="youtube_api_key" rows="3" required>{{ old('youtube_api_key', $apiKey?->value) }}</textarea>
        @error('youtube_api_key')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">{{ __('admin.youtube_api_key_help') }}</div>
    </div>

    <div class="mb-3">
        <label for="admin_notification_emails" class="form-label">{{ __('admin.admin_notification_emails') }}</label>
        <textarea class="form-control @error('admin_notification_emails') is-invalid @enderror" id="admin_notification_emails" name="admin_notification_emails" rows="2">{{ old('admin_notification_emails', $adminNotificationEmails?->value) }}</textarea>
        @error('admin_notification_emails')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">{{ __('admin.admin_notification_emails_help') }}</div>
    </div>
    
    <hr class="my-4">
    
    <h5 class="mb-3">{{ __('admin.google_cloud_setup_instructions') }}</h5>
    
    <div class="mb-3">
        <label for="google_cloud_project_id" class="form-label">{{ __('admin.google_cloud_project_id') }}</label>
        <input type="text" class="form-control @error('google_cloud_project_id') is-invalid @enderror" id="google_cloud_project_id" name="google_cloud_project_id" value="{{ old('google_cloud_project_id', $googleCloudProjectId?->value) }}">
        @error('google_cloud_project_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">{{ __('admin.google_cloud_project_id_help') }}</div>
    </div>
    
    <div class="mb-3">
        <label for="google_cloud_service_account" class="form-label">{{ __('admin.google_cloud_service_account') }}</label>
        <textarea class="form-control @error('google_cloud_service_account') is-invalid @enderror" id="google_cloud_service_account" name="google_cloud_service_account" rows="6" style="font-family: monospace; font-size: 0.875rem;">{{ old('google_cloud_service_account', $googleCloudServiceAccount?->value) }}</textarea>
        @error('google_cloud_service_account')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">{{ __('admin.google_cloud_service_account_help') }}</div>
    </div>
    
    <div class="alert alert-info">
        <h6 class="alert-heading">{{ __('admin.google_cloud_setup_instructions') }}</h6>
        <ol class="mb-0">
            <li>{{ __('admin.google_cloud_step_1') }}</li>
            <li>{{ __('admin.google_cloud_step_2') }}</li>
            <li>{{ __('admin.google_cloud_step_3') }}</li>
            <li>{{ __('admin.google_cloud_step_4') }}</li>
            <li>{{ __('admin.google_cloud_step_5') }}</li>
            <li>{{ __('admin.google_cloud_step_6') }}</li>
        </ol>
    </div>
    
    <button type="submit" class="btn btn-success w-100 w-md-auto">{{ __('admin.save_settings') }}</button>
</form>

{{-- Cache Management Section --}}
<hr class="my-4">

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">{{ __('admin.cache_management') }}</h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">{{ __('admin.cache_management_description') }}</p>
        <form method="POST" action="{{ route('admin.settings.clear-cache') }}" onsubmit="return confirm('{{ __('admin.cache_clear_confirm') }}');">
            @csrf
            <button type="submit" class="btn btn-warning">
                <i class="bi bi-trash"></i> {{ __('admin.clear_cache') }}
            </button>
        </form>
    </div>
</div>
@endsection

