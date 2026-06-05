/**
 * Dashboard Module
 * Handles tab switching, data loading, and rendering
 */

import { makeRequest } from '../../core/utils.js';

class Dashboard {
    constructor() {
        this.data = window.dashboardData || {};
        this.activityData = null;
        this.contentData = null;
        this.currentPeriod = 'week';
        this.currentOffset = 0;
        this.init();
    }

    init() {
        // Load activity data on page load
        this.loadActivityData();

        // Setup tab switching
        this.setupTabs();
    }

    setupTabs() {
        const tabs = document.querySelectorAll('#dashboardTabs button[data-bs-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', (e) => {
                const targetId = e.target.getAttribute('data-bs-target');
                
                if (targetId === '#activity' && !this.activityData) {
                    this.loadActivityData();
                } else if (targetId === '#content' && !this.contentData) {
                    this.loadContentData();
                }
            });
        });
    }

    async loadActivityData() {
        const container = document.getElementById('activityContent');
        if (!container) return;

        try {
            const url = `${this.data.activityUrl}?slug=${encodeURIComponent(this.data.slug)}&period=${encodeURIComponent(this.currentPeriod)}&offset=${this.currentOffset}`;
            const response = await makeRequest(url, {
                method: 'GET',
            });

            // makeRequest returns {data: {...}, headers: {...}, xhr: {...}}
            // The actual server response is in response.data
            const serverResponse = response?.data;
            
            if (serverResponse?.success && serverResponse?.data) {
                this.activityData = serverResponse.data;
                this.renderActivityOverview(container, serverResponse.data);
            } else {
                const errorMsg = serverResponse?.message || 'Failed to load activity data';
                console.error('[Dashboard] Activity data response:', serverResponse);
                this.renderError(container, errorMsg);
            }
        } catch (error) {
            console.error('[Dashboard] Failed to load activity data:', error);
            const errorMsg = error?.responseData?.message || error?.message || 'Error loading activity data';
            this.renderError(container, errorMsg);
        }
    }

    async loadContentData() {
        const container = document.getElementById('contentContent');
        if (!container) return;

        try {
            const url = `${this.data.contentUrl}?slug=${encodeURIComponent(this.data.slug)}`;
            const response = await makeRequest(url, {
                method: 'GET',
            });

            // makeRequest returns {data: {...}, headers: {...}, xhr: {...}}
            // The actual server response is in response.data
            const serverResponse = response?.data;
            
            if (serverResponse?.success && serverResponse?.data) {
                this.contentData = serverResponse.data;
                this.renderContentInsights(container, serverResponse.data);
            } else {
                const errorMsg = serverResponse?.message || 'Failed to load content data';
                console.error('[Dashboard] Content data response:', serverResponse);
                this.renderError(container, errorMsg);
            }
        } catch (error) {
            console.error('[Dashboard] Failed to load content data:', error);
            const errorMsg = error?.responseData?.message || error?.message || 'Error loading content data';
            this.renderError(container, errorMsg);
        }
    }

    renderActivityOverview(container, data) {
        const activity = data.activity;
        const recentActivity = data.recent_activity || [];
        const sessionStats = data.session_stats || {};
        const periodMetadata = data.period_metadata || { has_previous: true, has_next: false };

        container.innerHTML = `
            <div class="dashboard-activity-overview">
                <!-- Period Selector -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <label class="mb-0 fw-bold">Period:</label>
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="period" id="period-week" value="week" ${this.currentPeriod === 'week' ? 'checked' : ''}>
                                    <label class="btn btn-outline-primary" for="period-week">Week</label>
                                    
                                    <input type="radio" class="btn-check" name="period" id="period-month" value="month" ${this.currentPeriod === 'month' ? 'checked' : ''}>
                                    <label class="btn btn-outline-primary" for="period-month">Month</label>
                                    
                                    <input type="radio" class="btn-check" name="period" id="period-year" value="year" ${this.currentPeriod === 'year' ? 'checked' : ''}>
                                    <label class="btn btn-outline-primary" for="period-year">Year</label>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary" id="btn-previous-period" ${!periodMetadata.has_previous ? 'disabled' : ''}>
                                    <span aria-hidden="true">&larr;</span> Previous
                                </button>
                                <span class="fw-bold" id="period-label">${activity.period_label || ''}</span>
                                <button type="button" class="btn btn-outline-secondary" id="btn-next-period" ${!periodMetadata.has_next ? 'disabled' : ''}>
                                    Next <span aria-hidden="true">&rarr;</span>
                                </button>
                                ${this.currentOffset !== 0 ? `<button type="button" class="btn btn-outline-primary btn-sm" id="btn-current-period">Current</button>` : ''}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Today</h6>
                                <h4 class="mb-0">${this.formatTime(activity.today?.watch_time || 0)}</h4>
                                <small class="text-muted">${activity.today?.sessions || 0} sessions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Selected Period</h6>
                                <h4 class="mb-0">${this.formatTime(activity.period_stats?.watch_time || 0)}</h4>
                                <small class="text-muted">${activity.period_stats?.sessions || 0} sessions</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Average</h6>
                                <h4 class="mb-0">${this.formatTime(activity.average?.session_length || 0)}</h4>
                                <small class="text-muted">per session</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Period Chart -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Watch Time - ${this.getPeriodChartTitle(activity.period)}</h5>
                    </div>
                    <div class="card-body">
                        <div class="period-chart">
                            ${this.renderPeriodChart(activity.period_data || [], activity.period)}
                        </div>
                    </div>
                </div>

                <!-- Peak Viewing Hours and Day Patterns -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Peak Viewing Times</h5>
                            </div>
                            <div class="card-body">
                                <div class="peak-hours-chart">
                                    ${this.renderPeakHours(activity.peak_hours || [])}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Most Active Days</h5>
                            </div>
                            <div class="card-body">
                                <div class="day-patterns-chart">
                                    ${this.renderDayPatterns(activity.day_of_week_patterns || {})}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="recent-activity-list">
                            ${this.renderRecentActivity(recentActivity)}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderContentInsights(container, data) {
        container.innerHTML = `
            <div class="dashboard-content-insights">
                <!-- Most Watched Videos -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Most Watched Videos</h5>
                    </div>
                    <div class="card-body">
                        ${this.renderMostWatchedVideos(data.most_watched_videos || [])}
                    </div>
                </div>

                <!-- Top Channels and Playlists -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Top Channels</h5>
                            </div>
                            <div class="card-body">
                                ${this.renderTopChannels(data.top_channels || [])}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Most Watched Playlists</h5>
                            </div>
                            <div class="card-body">
                                ${this.renderMostWatchedPlaylists(data.most_watched_playlists || [])}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Re-watch Favorites -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Re-watch Favorites</h5>
                        <small class="text-muted">Videos watched 5+ times</small>
                    </div>
                    <div class="card-body">
                        ${this.renderRewatchFavorites(data.rewatch_favorites || [])}
                    </div>
                </div>

                <!-- Completion Rates -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Completion Rates</h5>
                    </div>
                    <div class="card-body">
                        ${this.renderCompletionRates(data.completion_rates || {})}
                    </div>
                </div>
            </div>
        `;
    }

    renderPeriodChart(periodData, period) {
        if (periodData.length === 0) {
            return '<p class="text-muted">No data available</p>';
        }

        const maxTime = Math.max(...periodData.map(d => d.watch_time), 1);
        
        // For year view, use monthly bars; for week/month, use daily bars
        const isYearly = period === 'year';
        const barWidth = isYearly ? '8%' : 'auto';
        const maxBarWidth = isYearly ? '80px' : '60px';
        
        return `
            <div class="period-chart-bars d-flex align-items-end gap-2" style="height: 200px; flex-wrap: ${isYearly ? 'wrap' : 'nowrap'};">
                ${periodData.map(item => {
                    const height = maxTime > 0 ? (item.watch_time / maxTime) * 100 : 0;
                    const label = item.label || item.day || item.date;
                    return `
                        <div class="d-flex flex-column align-items-center" style="flex: ${isYearly ? '0 0 auto' : '1 1 0'}; min-width: ${isYearly ? '80px' : '0'};">
                            <div class="bar-container flex-fill d-flex align-items-end w-100" style="max-width: ${maxBarWidth}; width: ${barWidth};">
                                <div class="bar bg-success w-100 rounded-top" style="height: ${height}%; min-height: ${item.watch_time > 0 ? '4px' : '0'};" title="${this.formatTime(item.watch_time)}"></div>
                            </div>
                            <small class="mt-2 text-muted text-center" style="font-size: 0.75rem;">${label}</small>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderPeakHours(hours) {
        if (hours.length === 0) {
            return '<p class="text-muted">No data available</p>';
        }

        const maxCount = Math.max(...hours, 1);
        
        return `
            <div class="peak-hours-grid">
                ${hours.map((count, hour) => {
                    const intensity = maxCount > 0 ? (count / maxCount) * 100 : 0;
                    const opacity = Math.max(0.2, intensity / 100);
                    return `
                        <div class="hour-cell" style="opacity: ${opacity}; background-color: rgba(25, 135, 84, ${opacity});" title="Hour ${hour}: ${count} videos">
                            ${hour}
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderDayPatterns(patterns) {
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        const maxTime = Math.max(...Object.values(patterns), 1);
        
        return `
            <div class="day-patterns-bars">
                ${days.map(day => {
                    const time = patterns[day] || 0;
                    const width = maxTime > 0 ? (time / maxTime) * 100 : 0;
                    return `
                        <div class="mb-2">
                            <div class="d-flex justify-content-between mb-1">
                                <small>${day.substring(0, 3)}</small>
                                <small class="text-muted">${this.formatTime(time)}</small>
                            </div>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: ${width}%;" aria-valuenow="${width}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderRecentActivity(activities) {
        if (activities.length === 0) {
            return '<p class="text-muted">No recent activity</p>';
        }

        return `
            <div class="list-group">
                ${activities.map(activity => {
                    const eventType = activity.event_type === 'started' ? 'Started watching' : activity.event_type === 'completed' ? 'Completed' : activity.event_type;
                    const videoTitle = activity.video?.title || 'Unknown video';
                    const deviceName = activity.device_name ? ` on ${activity.device_name}` : '';
                    const date = new Date(activity.created_at);
                    return `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${eventType}: ${videoTitle}</h6>
                                    <small class="text-muted">${deviceName}</small>
                                </div>
                                <small class="text-muted">${this.formatDate(date)}</small>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderMostWatchedVideos(videos) {
        if (videos.length === 0) {
            return '<p class="text-muted">No videos watched yet</p>';
        }

        return `
            <div class="list-group">
                ${videos.map((item, index) => {
                    const video = item.video;
                    return `
                        <div class="list-group-item">
                            <div class="d-flex align-items-center gap-3">
                                <div class="flex-shrink-0">
                                    <span class="badge bg-success">${index + 1}</span>
                                </div>
                                ${video.thumbnail_url ? `<img src="${video.thumbnail_url}" alt="${video.title}" class="rounded" style="width: 80px; height: 60px; object-fit: cover;">` : ''}
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${video.title}</h6>
                                    <small class="text-muted">
                                        Watched ${item.watch_count} times • 
                                        ${this.formatTime(item.total_watch_time)} total • 
                                        ${item.avg_completion}% avg completion
                                    </small>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderTopChannels(channels) {
        if (channels.length === 0) {
            return '<p class="text-muted">No channel data available</p>';
        }

        return `
            <div class="list-group">
                ${channels.map((channel, index) => `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${index + 1}. ${channel.channel_name}</h6>
                                <small class="text-muted">${channel.watch_count} videos • ${this.formatTime(channel.watch_time)}</small>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    renderMostWatchedPlaylists(playlists) {
        if (playlists.length === 0) {
            return '<p class="text-muted">No playlist data available</p>';
        }

        return `
            <div class="list-group">
                ${playlists.map((item, index) => {
                    const playlist = item.playlist;
                    return `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">${index + 1}. ${playlist.title}</h6>
                                    <small class="text-muted">
                                        ${item.videos_watched} videos • 
                                        ${item.total_starts} starts • 
                                        ${item.avg_videos_per_session} avg per session
                                    </small>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderRewatchFavorites(videos) {
        if (videos.length === 0) {
            return '<p class="text-muted">No re-watch favorites yet</p>';
        }

        return `
            <div class="row g-3">
                ${videos.map(item => {
                    const video = item.video;
                    return `
                        <div class="col-md-3 col-sm-4 col-6">
                            <div class="card">
                                ${video.thumbnail_url ? `<img src="${video.thumbnail_url}" class="card-img-top" alt="${video.title}" style="height: 120px; object-fit: cover;">` : ''}
                                <div class="card-body p-2">
                                    <h6 class="card-title small mb-1" style="font-size: 0.85rem;">${video.title}</h6>
                                    <span class="badge bg-success">${item.watch_count}x</span>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderCompletionRates(rates) {
        const total = rates.total_started || 0;
        const completed = rates.fully_watched || 0;
        const partial = rates.partially_watched || 0;
        const completionRate = rates.completion_rate || 0;

        return `
            <div class="completion-stats">
                <div class="mb-3">
                    <h6>Overall Completion Rate: ${completionRate}%</h6>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar" role="progressbar" style="width: ${completionRate}%;" aria-valuenow="${completionRate}" aria-valuemin="0" aria-valuemax="100">
                            ${completionRate}%
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h4 class="mb-0">${total}</h4>
                                <small class="text-muted">Total Started</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h4 class="mb-0">${completed}</h4>
                                <small class="text-muted">Fully Watched</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h4 class="mb-0">${partial}</h4>
                                <small class="text-muted">Partially Watched</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderError(container, message) {
        container.innerHTML = `
            <div class="alert alert-danger" role="alert">
                ${message}
            </div>
        `;
    }

    formatTime(seconds) {
        if (!seconds || seconds === 0) return '0m';
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        }
        return `${minutes}m`;
    }

    formatDate(date) {
        const now = new Date();
        const diff = now - date;
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);

        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        if (hours < 24) return `${hours}h ago`;
        if (days < 7) return `${days}d ago`;
        return date.toLocaleDateString();
    }
}

// Initialize dashboard when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new Dashboard();
    });
} else {
    new Dashboard();
}

