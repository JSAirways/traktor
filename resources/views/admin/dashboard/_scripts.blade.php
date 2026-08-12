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
