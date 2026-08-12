/**
 * Dashboard Module
 * Single-page analytics overview with shared date range
 */

import { makeRequest } from '../../core/utils.js';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

class Dashboard {
    constructor() {
        this.data = window.dashboardData || {};
        this.i18n = this.data.i18n || {};
        this.preset = '28';
        this.startDate = null;
        this.endDate = null;
        this.loading = false;
        this.flatpickr = null;
        this.init();
    }

    init() {
        this.applyPreset(28, { reload: false });
        this.setupRangeControls();
        this.loadDashboard();
    }

    t(key, fallback = '') {
        return this.i18n[key] || fallback;
    }

    formatDateParam(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    startOfDay(date) {
        const d = new Date(date);
        d.setHours(0, 0, 0, 0);
        return d;
    }

    applyPreset(days, { reload = true } = {}) {
        const end = this.startOfDay(new Date());
        const start = this.startOfDay(new Date());
        start.setDate(end.getDate() - (days - 1));
        this.preset = String(days);
        this.startDate = start;
        this.endDate = end;
        this.syncRangeUi();
        if (reload) {
            this.loadDashboard();
        }
    }

    setupRangeControls() {
        const pickerEl = document.getElementById('dashboardDateRangePicker');
        const applyBtn = document.getElementById('dashboardDateRangeApply');
        const previewEl = document.getElementById('dashboardDateRangePreview');
        const modalEl = document.getElementById('dashboardDateRangeModal');

        if (pickerEl) {
            this.flatpickr = flatpickr(pickerEl, {
                mode: 'range',
                inline: true,
                dateFormat: 'Y-m-d',
                maxDate: 'today',
                defaultDate: [this.startDate, this.endDate],
                appendTo: pickerEl,
                onChange: (selectedDates) => {
                    const complete = selectedDates.length === 2;
                    if (applyBtn) {
                        applyBtn.disabled = !complete;
                    }
                    if (previewEl) {
                        previewEl.textContent = complete
                            ? this.formatRangeLabel(selectedDates[0], selectedDates[1])
                            : '';
                    }
                },
            });
        }

        applyBtn?.addEventListener('click', () => {
            const selectedDates = this.flatpickr?.selectedDates || [];
            if (selectedDates.length !== 2) {
                return;
            }
            this.preset = 'custom';
            this.startDate = this.startOfDay(selectedDates[0]);
            this.endDate = this.startOfDay(selectedDates[1]);
            this.syncRangeUi();
            this.hideDateRangeModal();
            this.loadDashboard();
        });

        modalEl?.addEventListener('shown.bs.modal', () => {
            if (this.flatpickr && this.startDate && this.endDate) {
                this.flatpickr.setDate([this.startDate, this.endDate], true);
            }
        });

        document.querySelectorAll('.dashboard-range-preset').forEach((button) => {
            button.addEventListener('click', () => {
                if (this.loading) {
                    return;
                }
                const preset = button.dataset.preset;
                if (preset === 'custom') {
                    this.openDateRangeModal();
                    return;
                }
                this.applyPreset(Number(preset));
            });
        });
    }

    openDateRangeModal() {
        const modalEl = document.getElementById('dashboardDateRangeModal');
        if (!modalEl || !window.bootstrap?.Modal) {
            return;
        }
        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    hideDateRangeModal() {
        const modalEl = document.getElementById('dashboardDateRangeModal');
        if (!modalEl || !window.bootstrap?.Modal) {
            return;
        }
        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
    }

    formatRangeLabel(start, end) {
        const startLabel = this.startOfDay(start).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
        const endLabel = this.startOfDay(end).toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
        return `${startLabel} – ${endLabel}`;
    }

    syncRangeUi() {
        document.querySelectorAll('.dashboard-range-preset').forEach((button) => {
            button.classList.toggle('active', button.dataset.preset === this.preset);
        });

        const labelEl = document.getElementById('dashboardCustomRangeLabel');
        if (labelEl) {
            if (this.preset === 'custom' && this.startDate && this.endDate) {
                labelEl.textContent = this.formatRangeLabel(this.startDate, this.endDate);
                labelEl.classList.remove('d-none');
            } else {
                labelEl.textContent = '';
                labelEl.classList.add('d-none');
            }
        }
    }

    setLoading(isLoading) {
        this.loading = isLoading;
        const controls = document.getElementById('dashboardRangeControls');
        if (controls) {
            controls.querySelectorAll('button').forEach((el) => {
                el.disabled = isLoading;
            });
        }
    }

    showLoading() {
        const container = document.getElementById('dashboardContent');
        if (!container) {
            return;
        }
        container.innerHTML = `
            <div class="text-center py-5 dashboard-loading">
                <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">${this.escapeHtml(this.t('loading'))}</span>
                </div>
                <p class="mt-3 text-muted">${this.escapeHtml(this.t('loading'))}</p>
            </div>
        `;
    }

    async loadDashboard() {
        const container = document.getElementById('dashboardContent');
        if (!container || !this.startDate || !this.endDate) {
            return;
        }

        this.setLoading(true);
        this.showLoading();

        try {
            const params = new URLSearchParams({
                slug: this.data.slug || '',
                start: this.formatDateParam(this.startDate),
                end: this.formatDateParam(this.endDate),
            });
            const response = await makeRequest(`${this.data.dashboardUrl}?${params.toString()}`, {
                method: 'GET',
            });
            const serverResponse = response?.data;

            if (serverResponse?.success && serverResponse?.data) {
                this.renderDashboard(container, serverResponse.data);
            } else {
                this.renderError(container, serverResponse?.message || this.t('loadError'));
            }
        } catch (error) {
            console.error('[Dashboard] Failed to load data:', error);
            this.renderError(
                container,
                error?.responseData?.message || error?.message || this.t('loadError')
            );
        } finally {
            this.setLoading(false);
        }
    }

    renderDashboard(container, data) {
        const range = data.range || {};
        const kpis = data.kpis || {};
        const granularityLabel = this.t(range.granularity || 'daily', range.granularity || 'daily');

        container.innerHTML = `
            <div class="dashboard-overview">
                <div class="row g-3 mb-4 dashboard-kpi-strip">
                    ${this.renderKpiCard(this.t('watchTime'), this.formatTime(kpis.watch_time || 0))}
                    ${this.renderKpiCard(this.t('sessions'), String(kpis.sessions || 0))}
                    ${this.renderKpiCard(this.t('avgSession'), this.formatTime(kpis.avg_session_length || 0))}
                    ${this.renderKpiCard(this.t('videoStarts'), String(kpis.video_starts || 0))}
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-1">
                        <h5 class="mb-0">${this.escapeHtml(this.t('watchTimeOverTime'))}</h5>
                        <small class="text-muted">${this.escapeHtml(range.label || '')} · ${this.escapeHtml(granularityLabel)}</small>
                    </div>
                    <div class="card-body">
                        ${this.renderWatchTimeChart(data.watch_time_series || [])}
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">${this.escapeHtml(this.t('peakViewingTimes'))}</h5>
                                ${this.renderPeakCallout(data.peak_hours || [])}
                            </div>
                            <div class="card-body">
                                ${this.renderPeakHours(data.peak_hours || [])}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">${this.escapeHtml(this.t('mostActiveWeekdays'))}</h5>
                            </div>
                            <div class="card-body">
                                ${this.renderDayPatterns(data.day_of_week_patterns || {})}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-2">
                    <h4 class="h5 mb-3">${this.escapeHtml(this.t('whatTheyWatched'))}</h4>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">${this.escapeHtml(this.t('mostWatchedVideos'))}</h5>
                    </div>
                    <div class="card-body p-0">
                        ${this.renderRankedVideos(data.most_watched_videos || [])}
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">${this.escapeHtml(this.t('topChannels'))}</h5>
                            </div>
                            <div class="card-body p-0">
                                ${this.renderTopChannels(data.top_channels || [])}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <h5 class="mb-1">${this.escapeHtml(this.t('rewatchFavorites'))}</h5>
                                <small class="text-muted">${this.escapeHtml(this.t('videosWatched5Plus'))}</small>
                            </div>
                            <div class="card-body p-0">
                                ${this.renderRankedVideos(data.rewatch_favorites || [])}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">${this.escapeHtml(this.t('recentActivity'))}</h5>
                    </div>
                    <div class="card-body">
                        ${this.renderRecentActivity(data.recent_activity || [])}
                    </div>
                </div>
            </div>
        `;
    }

    renderKpiCard(label, value) {
        return `
            <div class="col-6 col-lg-3">
                <div class="card h-100 dashboard-kpi-card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">${this.escapeHtml(label)}</h6>
                        <h3 class="mb-0">${this.escapeHtml(value)}</h3>
                    </div>
                </div>
            </div>
        `;
    }

    renderWatchTimeChart(series) {
        if (!series.length) {
            return `<p class="text-muted mb-0">${this.escapeHtml(this.t('noData'))}</p>`;
        }

        const maxTime = Math.max(...series.map((d) => d.watch_time), 0);
        const midTime = Math.round(maxTime / 2);
        const ticks = [maxTime, midTime, 0];

        return `
            <div class="axis-chart">
                <div class="axis-chart-y" aria-hidden="true">
                    ${ticks.map((tick) => `<span>${this.escapeHtml(this.formatTime(tick))}</span>`).join('')}
                </div>
                <div class="axis-chart-plot">
                    <div class="axis-chart-grid" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </div>
                    <div class="axis-chart-bars">
                        ${series.map((item) => {
                            const height = maxTime > 0 ? (item.watch_time / maxTime) * 100 : 0;
                            return `
                                <div class="axis-chart-bar-col">
                                    <div class="axis-chart-bar-track">
                                        <div class="axis-chart-bar" style="height: ${height}%;" title="${this.escapeHtml(this.formatTime(item.watch_time))}"></div>
                                    </div>
                                    <small class="axis-chart-x-label">${this.escapeHtml(item.label || item.date || '')}</small>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            </div>
        `;
    }

    renderPeakCallout(hours) {
        if (!hours.length || Math.max(...hours) <= 0) {
            return '';
        }
        const peakHour = hours.indexOf(Math.max(...hours));
        return `<small class="text-muted d-block mt-1">${this.escapeHtml(this.t('peak'))}: ${this.escapeHtml(this.formatHourRange(peakHour))}</small>`;
    }

    renderPeakHours(hours) {
        if (!hours.length || Math.max(...hours) <= 0) {
            return `<p class="text-muted mb-0">${this.escapeHtml(this.t('noData'))}</p>`;
        }

        const rawMax = Math.max(...hours);
        const scaleMax = this.niceCountScaleMax(rawMax);
        const yTicks = this.countAxisTicks(scaleMax);
        const xLabels = [
            { hour: 0, label: this.formatHourLabel(0) },
            { hour: 6, label: this.formatHourLabel(6) },
            { hour: 12, label: this.formatHourLabel(12) },
            { hour: 18, label: this.formatHourLabel(18) },
        ];

        return `
            <div class="peak-hours-chart">
                <div class="peak-hours-body">
                    <div class="peak-hours-y" aria-hidden="true">
                        ${yTicks.map((tick) => `<span>${tick}</span>`).join('')}
                    </div>
                    <div class="peak-hours-plot">
                        <div class="peak-hours-grid" aria-hidden="true">
                            ${yTicks.map(() => '<span></span>').join('')}
                        </div>
                        <div class="peak-hours-bars">
                            ${hours.map((count, hour) => {
                                const height = scaleMax > 0 ? (count / scaleMax) * 100 : 0;
                                const title = `${this.formatHourRange(hour)} · ${count} ${this.t('starts')}`;
                                return `
                                    <div class="peak-hours-bar-col" title="${this.escapeHtml(title)}">
                                        <div class="peak-hours-bar" style="height: ${height}%;"></div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                </div>
                <div class="peak-hours-x" aria-hidden="true">
                    ${xLabels.map((item) => `
                        <span style="left: ${(item.hour / 24) * 100}%;">${this.escapeHtml(item.label)}</span>
                    `).join('')}
                </div>
                <div class="peak-hours-unit text-muted">${this.escapeHtml(this.t('starts'))}</div>
            </div>
        `;
    }

    /**
     * Round a count scale up so top tick is a clean integer.
     */
    niceCountScaleMax(max) {
        if (max <= 1) return 1;
        if (max <= 2) return 2;
        if (max <= 4) return 4;
        if (max <= 5) return 5;
        if (max <= 10) return 10;
        return Math.ceil(max / 5) * 5;
    }

    /**
     * Integer Y-axis ticks from scale max down to 0 (no duplicates).
     */
    countAxisTicks(scaleMax) {
        if (scaleMax <= 1) {
            return [1, 0];
        }
        if (scaleMax === 2) {
            return [2, 1, 0];
        }
        if (scaleMax <= 5) {
            return [scaleMax, Math.round(scaleMax / 2), 0];
        }
        return [scaleMax, Math.round(scaleMax / 2), 0];
    }

    renderDayPatterns(patterns) {
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        const values = days.map((day) => patterns[day] || 0);
        if (Math.max(...values) <= 0) {
            return `<p class="text-muted mb-0">${this.escapeHtml(this.t('noData'))}</p>`;
        }

        const maxTime = Math.max(...values, 1);

        return `
            <div class="day-patterns-bars">
                ${days.map((day) => {
                    const time = patterns[day] || 0;
                    const width = maxTime > 0 ? (time / maxTime) * 100 : 0;
                    return `
                        <div class="mb-2">
                            <div class="d-flex justify-content-between mb-1">
                                <small>${this.escapeHtml(day.substring(0, 3))}</small>
                                <small class="text-muted">${this.escapeHtml(this.formatTime(time))}</small>
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

    renderRankedVideos(items) {
        if (!items.length) {
            return `<p class="text-muted p-3 mb-0">${this.escapeHtml(this.t('noData'))}</p>`;
        }

        return `
            <div class="list-group list-group-flush ranked-content-list">
                ${items.map((item, index) => {
                    const video = item.video || {};
                    const meta = [
                        `${this.t('watched')} ${item.watch_count || 0} ${this.t('times')}`,
                        item.total_watch_time != null ? `${this.formatTime(item.total_watch_time)} ${this.t('total')}` : null,
                    ].filter(Boolean).join(' · ');

                    return `
                        <div class="list-group-item">
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success">${index + 1}</span>
                                ${video.thumbnail_url
                                    ? `<img src="${this.escapeHtml(video.thumbnail_url)}" alt="" class="rounded ranked-thumb">`
                                    : '<div class="rounded ranked-thumb ranked-thumb-placeholder"></div>'}
                                <div class="flex-grow-1 min-w-0">
                                    <h6 class="mb-1 text-truncate">${this.escapeHtml(video.title || 'Unknown video')}</h6>
                                    <small class="text-muted">${this.escapeHtml(meta)}</small>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderTopChannels(channels) {
        if (!channels.length) {
            return `<p class="text-muted p-3 mb-0">${this.escapeHtml(this.t('noData'))}</p>`;
        }

        return `
            <div class="list-group list-group-flush ranked-content-list">
                ${channels.map((channel, index) => `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <h6 class="mb-1 text-truncate">
                                    <span class="badge bg-success me-2">${index + 1}</span>
                                    ${this.escapeHtml(channel.channel_name || 'Unknown channel')}
                                </h6>
                                <small class="text-muted">
                                    ${channel.watch_count || 0} ${this.escapeHtml(this.t('videos'))} · ${this.escapeHtml(this.formatTime(channel.watch_time || 0))}
                                </small>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    renderRecentActivity(activities) {
        if (!activities.length) {
            return `<p class="text-muted mb-0">${this.escapeHtml(this.t('noRecentActivity'))}</p>`;
        }

        return `
            <div class="list-group list-group-flush">
                ${activities.map((activity) => {
                    const eventType = activity.event_type === 'started'
                        ? this.t('startedWatching')
                        : activity.event_type === 'completed'
                            ? this.t('completed')
                            : activity.event_type;
                    const videoTitle = activity.video?.title || 'Unknown video';
                    const deviceName = activity.device_name ? ` · ${activity.device_name}` : '';
                    const date = new Date(activity.created_at);

                    return `
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div class="min-w-0">
                                    <h6 class="mb-1 text-truncate">${this.escapeHtml(eventType)}: ${this.escapeHtml(videoTitle)}</h6>
                                    <small class="text-muted">${this.escapeHtml(deviceName.trim())}</small>
                                </div>
                                <small class="text-muted text-nowrap">${this.escapeHtml(this.formatDate(date))}</small>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    }

    renderError(container, message) {
        container.innerHTML = `
            <div class="alert alert-danger" role="alert">
                ${this.escapeHtml(message)}
            </div>
        `;
    }

    formatHourLabel(hour) {
        const period = hour >= 12 ? 'PM' : 'AM';
        const display = hour % 12 === 0 ? 12 : hour % 12;
        return `${display} ${period}`;
    }

    formatHourRange(hour) {
        const start = this.formatHourLabel(hour);
        const endHour = (hour + 1) % 24;
        const end = this.formatHourLabel(endHour);
        return `${start} – ${end}`;
    }

    formatTime(seconds) {
        if (!seconds || seconds === 0) {
            return '0m';
        }
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

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new Dashboard();
    });
} else {
    new Dashboard();
}
