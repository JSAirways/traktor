@extends('layouts.admin')

@section('content')
<div class="dashboard-page">
    <h2 class="mb-3 mb-md-3">{{ __('admin.dashboard') }}</h2>

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center mb-3 mb-md-4 gap-3">
        <x-ui.user-selector
            :users="$availableUsers"
            :selected="$displayUser"
            :route="route('admin.dashboard.index')"
            param="slug"
            value-key="slug"
            id="dashboardUserSelector"
            :aria-label="__('admin.viewing_analytics_for')"
        />

        <div class="dashboard-range-controls d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2" id="dashboardRangeControls">
            <div class="btn-group" role="group" aria-label="{{ __('admin.date_range') }}">
                <button type="button" class="btn btn-outline-success btn-sm dashboard-range-preset" data-preset="7">{{ __('admin.last_7_days') }}</button>
                <button type="button" class="btn btn-outline-success btn-sm dashboard-range-preset active" data-preset="28">{{ __('admin.last_28_days') }}</button>
                <button type="button" class="btn btn-outline-success btn-sm dashboard-range-preset" data-preset="90">{{ __('admin.last_90_days') }}</button>
                <button type="button" class="btn btn-outline-success btn-sm dashboard-range-preset" data-preset="custom" id="dashboardCustomRangeBtn">{{ __('admin.custom_range') }}</button>
            </div>
            <small class="text-muted dashboard-custom-range-label d-none" id="dashboardCustomRangeLabel"></small>
        </div>
    </div>

    <div id="dashboardContent" class="dashboard-loading">
        <div class="text-center py-5">
            <x-ui.loading-spinner :text="__('admin.loading_analytics')" />
        </div>
    </div>
</div>

{{-- Custom date range modal --}}
<div class="modal fade" id="dashboardDateRangeModal" tabindex="-1" aria-labelledby="dashboardDateRangeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dashboardDateRangeModalLabel">{{ __('admin.select_date_range') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('common.close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">{{ __('admin.select_date_range_help') }}</p>
                <div id="dashboardDateRangePicker" class="dashboard-date-range-picker"></div>
                <p class="text-muted small mt-3 mb-0" id="dashboardDateRangePreview"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('common.cancel') }}</button>
                <button type="button" class="btn btn-success" id="dashboardDateRangeApply" disabled>{{ __('admin.apply_date_range') }}</button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.dashboardData = {
        slug: @json($displayUser->slug),
        dashboardUrl: @json(route('admin.dashboard.data')),
        i18n: {
            loading: @json(__('admin.loading_analytics')),
            watchTime: @json(__('admin.watch_time')),
            sessions: @json(__('admin.sessions')),
            avgSession: @json(__('admin.avg_session')),
            videoStarts: @json(__('admin.video_starts')),
            watchTimeOverTime: @json(__('admin.watch_time_over_time')),
            peakViewingTimes: @json(__('admin.peak_viewing_times')),
            mostActiveWeekdays: @json(__('admin.most_active_weekdays')),
            whatTheyWatched: @json(__('admin.what_they_watched')),
            mostWatchedVideos: @json(__('admin.most_watched_videos')),
            topChannels: @json(__('admin.top_channels')),
            rewatchFavorites: @json(__('admin.rewatch_favorites')),
            videosWatched5Plus: @json(__('admin.videos_watched_5_plus_times')),
            recentActivity: @json(__('admin.recent_activity')),
            peak: @json(__('admin.peak')),
            starts: @json(__('admin.starts')),
            daily: @json(__('admin.granularity_daily')),
            weekly: @json(__('admin.granularity_weekly')),
            monthly: @json(__('admin.granularity_monthly')),
            noData: @json(__('admin.no_analytics_data')),
            noRecentActivity: @json(__('admin.no_recent_activity')),
            watched: @json(__('admin.watched')),
            times: @json(__('admin.times')),
            total: @json(__('admin.total')),
            videos: @json(__('admin.videos')),
            startedWatching: @json(__('admin.started_watching')),
            completed: @json(__('admin.completed_watching')),
            loadError: @json(__('admin.dashboard_load_error')),
        },
    };
</script>
@vite(['resources/js/admin/dashboard/index.js'])
@endpush
@endsection
