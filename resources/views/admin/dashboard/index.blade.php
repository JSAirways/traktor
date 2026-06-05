@extends('layouts.admin')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4 gap-2">
    <div class="d-flex flex-column gap-2">
        <h2 class="mb-0">{{ __('admin.dashboard') }}</h2>
        @if(count($availableUsers) > 1)
            <form method="GET" action="{{ route('admin.dashboard.index') }}" class="d-flex align-items-center gap-2">
                <label for="slug" class="form-label mb-0">{{ __('admin.viewing_analytics_for') }}</label>
                <select name="slug" id="slug" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    @foreach($availableUsers as $availableUser)
                        <option value="{{ $availableUser['slug'] }}" {{ $displayUser->slug === $availableUser['slug'] ? 'selected' : '' }}>
                            {{ $availableUser['username'] }}
                            @if($availableUser['id'] === auth()->id())
                                ({{ __('common.you') }})
                            @endif
                        </option>
                    @endforeach
                </select>
            </form>
        @else
            <p class="text-muted mb-0 small">{{ __('admin.viewing_analytics_for') }} <strong>{{ $displayUser->username }}</strong></p>
        @endif
    </div>
</div>

<!-- Tab Navigation -->
<ul class="nav nav-tabs mb-4" id="dashboardTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="true">
            {{ __('admin.activity_overview') }}
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="content-tab" data-bs-toggle="tab" data-bs-target="#content" type="button" role="tab" aria-controls="content" aria-selected="false">
            {{ __('admin.content_insights') }}
        </button>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="dashboardTabContent">
    <!-- Activity Overview Tab -->
    <div class="tab-pane fade show active" id="activity" role="tabpanel" aria-labelledby="activity-tab">
        <div id="activityContent" class="dashboard-loading">
            <div class="text-center py-5">
                <x-ui.loading-spinner />
                <p class="mt-3 text-muted">{{ __('admin.loading_analytics') }}</p>
            </div>
        </div>
    </div>

    <!-- Content Insights Tab -->
    <div class="tab-pane fade" id="content" role="tabpanel" aria-labelledby="content-tab">
        <div id="contentContent" class="dashboard-loading">
            <div class="text-center py-5">
                <x-ui.loading-spinner />
                <p class="mt-3 text-muted">{{ __('admin.loading_analytics') }}</p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Pass data to JavaScript
    window.dashboardData = {
        slug: '{{ $displayUser->slug }}',
        activityUrl: '{{ route("admin.dashboard.activity") }}',
        contentUrl: '{{ route("admin.dashboard.content") }}',
    };
</script>
@vite(['resources/js/admin/dashboard/index.js'])
@endpush
@endsection

