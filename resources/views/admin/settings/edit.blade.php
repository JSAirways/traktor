@extends('layouts.admin')

@php
    $hasGoogleCloudConfig = filled($googleCloudProjectId?->value) && filled($googleCloudServiceAccount?->value);
    $quotaEnabledSetting = $youtubeQuotaEnabled?->value;
    $isQuotaEnabled = $quotaEnabledSetting !== null
        ? filter_var($quotaEnabledSetting, FILTER_VALIDATE_BOOLEAN)
        : $hasGoogleCloudConfig;
    $googleCloudSetupOpen = $errors->has('google_cloud_project_id') || $errors->has('google_cloud_service_account');
@endphp

@push('styles')
    @vite(['resources/js/admin/settings/quota-monitor.js'])
@endpush

@section('content')
<x-admin.page-header :title="__('admin.settings')">
    <x-slot name="controls">
        <div></div>
        <button type="submit" form="settingsForm" class="btn btn-success">{{ __('admin.save_settings') }}</button>
    </x-slot>
</x-admin.page-header>

<form method="POST" action="{{ route('admin.settings.update') }}" id="settingsForm">
    @csrf
    @method('PUT')

    <div class="row mb-4">
        <div class="col-md-6 mb-3 mb-md-0">
            <label for="youtube_api_key" class="form-label">{{ __('admin.youtube_api_key') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('youtube_api_key') is-invalid @enderror" id="youtube_api_key" name="youtube_api_key" value="{{ old('youtube_api_key', $apiKey?->value) }}" required autocomplete="off">
            @error('youtube_api_key')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">{{ __('admin.youtube_api_key_help') }}</div>
        </div>
        <div class="col-md-6">
            <label for="admin_notification_emails" class="form-label">{{ __('admin.admin_notification_emails') }}</label>
            <input type="text" class="form-control @error('admin_notification_emails') is-invalid @enderror" id="admin_notification_emails" name="admin_notification_emails" value="{{ old('admin_notification_emails', $adminNotificationEmails?->value) }}" autocomplete="off">
            @error('admin_notification_emails')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div class="form-text">{{ __('admin.admin_notification_emails_help') }}</div>
        </div>
    </div>

    {{-- YouTube API Quota Monitoring Card --}}
    <div class="card mb-4" id="quotaCard"
         data-quota-configured="{{ $hasGoogleCloudConfig ? '1' : '0' }}"
         data-quota-enabled="{{ $isQuotaEnabled ? '1' : '0' }}">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="mb-0">{{ __('admin.youtube_quota_title') }}</h5>
            <div class="d-flex align-items-center gap-3">
                <button type="button"
                        class="btn btn-sm btn-outline-success {{ $isQuotaEnabled ? '' : 'd-none' }}"
                        id="refreshQuotaBtn"
                        data-refresh-text="{{ __('admin.youtube_quota_refresh') }}"
                        data-refreshing-text="{{ __('admin.youtube_quota_refreshing') }}">
                    <i class="bi bi-arrow-clockwise"></i> <span id="refreshBtnText">{{ __('admin.youtube_quota_refresh') }}</span>
                </button>
                <div class="form-check form-switch mb-0">
                    <input type="hidden" name="youtube_quota_enabled" value="0">
                    <input class="form-check-input"
                           type="checkbox"
                           role="switch"
                           id="youtubeQuotaEnabledToggle"
                           name="youtube_quota_enabled"
                           value="1"
                           {{ $isQuotaEnabled ? 'checked' : '' }}
                           aria-controls="quotaCardBody">
                    <label class="form-check-label" for="youtubeQuotaEnabledToggle">{{ __('admin.youtube_quota_enabled') }}</label>
                </div>
            </div>
        </div>

        <div class="card-body {{ $isQuotaEnabled ? '' : 'd-none' }}" id="quotaCardBody">
            <div id="quotaMonitoringPanel" class="{{ $hasGoogleCloudConfig ? '' : 'd-none' }}">
                <div id="quotaLoading" class="text-center py-3 d-none">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">{{ __('common.loading') }}</span>
                    </div>
                </div>

                <div id="quotaError" class="alert alert-warning d-none" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <span id="quotaErrorMessage"></span>
                </div>

                <div id="quotaContent" class="mb-4">
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

            <div id="quotaNotConfiguredAlert" class="alert alert-info {{ $hasGoogleCloudConfig ? 'd-none' : '' }}" role="alert">
                {{ __('admin.youtube_quota_not_configured') }}
            </div>

            <div class="border-top pt-3">
                <button class="btn btn-link text-decoration-none px-0"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#googleCloudSetupCollapse"
                        aria-expanded="{{ $googleCloudSetupOpen ? 'true' : 'false' }}"
                        aria-controls="googleCloudSetupCollapse">
                    <i class="bi bi-chevron-down me-1"></i>{{ __('admin.google_cloud_setup_toggle') }}
                </button>

                <div class="collapse {{ $googleCloudSetupOpen ? 'show' : '' }}" id="googleCloudSetupCollapse">
                    <div class="pt-2">
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

                        <div class="alert alert-info mb-0">
                            <h6 class="alert-heading">{{ __('admin.google_cloud_setup_instructions') }}</h6>
                            <ol class="mb-0">
                                <li>{{ __('admin.google_cloud_step_1') }}</li>
                                <li>{{ __('admin.google_cloud_step_2') }}</li>
                                <li>{{ __('admin.google_cloud_step_3') }}</li>
                                <li>{{ __('admin.google_cloud_step_4') }}</li>
                                <li>{{ __('admin.google_cloud_step_5') }}</li>
                                <li>{{ __('admin.google_cloud_step_6') }}</li>
                                <li>{{ __('admin.google_cloud_step_7') }}</li>
                                <li>{{ __('admin.google_cloud_step_8') }}</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
