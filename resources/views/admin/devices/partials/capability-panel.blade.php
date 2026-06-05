@php
    $capabilities = $capabilities ?? [];
    $hasCapabilities = is_array($capabilities) && count(array_filter($capabilities, fn ($value) => $value !== null && $value !== '')) > 0;

    $profileFields = [
        'language' => __('admin.capability_language'),
        'platform' => __('admin.capability_platform'),
        'connection_type' => __('admin.capability_connection'),
        'timezone_offset' => __('admin.capability_timezone_offset'),
        'screen' => __('admin.capability_screen'),
        'pixel_ratio' => __('admin.capability_pixel_ratio'),
        'hardware_concurrency' => __('admin.capability_hardware_concurrency'),
        'device_memory' => __('admin.capability_device_memory'),
    ];

    $featureFlags = [
        'touch_support' => [
            'label' => __('admin.capability_touch'),
            'icon' => 'bi-hand-index-thumb',
        ],
        'has_service_worker' => [
            'label' => __('admin.capability_service_worker'),
            'icon' => 'bi-cloud-check',
        ],
        'has_indexed_db' => [
            'label' => __('admin.capability_indexed_db'),
            'icon' => 'bi-database',
        ],
        'has_local_storage' => [
            'label' => __('admin.capability_local_storage'),
            'icon' => 'bi-hdd',
        ],
        'has_session_storage' => [
            'label' => __('admin.capability_session_storage'),
            'icon' => 'bi-hdd-stack',
        ],
        'has_webgl' => [
            'label' => __('admin.capability_webgl'),
            'icon' => 'bi-cpu',
        ],
        'has_autoplay_inline' => [
            'label' => __('admin.capability_inline_autoplay'),
            'icon' => 'bi-play-btn',
        ],
        'prefers_reduced_motion' => [
            'label' => __('admin.capability_reduced_motion'),
            'icon' => 'bi-arrow-repeat',
        ],
        'prefers_dark_mode' => [
            'label' => __('admin.capability_dark_mode'),
            'icon' => 'bi-moon-stars',
        ],
        'prefers_high_contrast' => [
            'label' => __('admin.capability_high_contrast'),
            'icon' => 'bi-brilliance',
        ],
    ];

    $screenSize = null;
    if (!empty($capabilities['screen_width']) && !empty($capabilities['screen_height'])) {
        $screenSize = $capabilities['screen_width'].'×'.$capabilities['screen_height'];
    }
@endphp

@if(!$hasCapabilities)
    <p class="text-muted mb-2">{{ __('admin.capabilities_not_reported') }}</p>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="alert">
        <i class="bi bi-info-circle fs-5"></i>
        <div class="small">
            {{ __('admin.capability_auto_refresh_note') }}
        </div>
    </div>
@else
    <div class="mb-4">
        <h6 class="text-uppercase small text-muted fw-semibold">{{ __('admin.capability_core_profile') }}</h6>
        <dl class="row mb-0 small">
            @if(!empty($capabilities['language']))
                <dt class="col-sm-4">{{ $profileFields['language'] }}</dt>
                <dd class="col-sm-8">{{ strtoupper($capabilities['language']) }}</dd>
            @endif

            @if(!empty($capabilities['platform']))
                <dt class="col-sm-4">{{ $profileFields['platform'] }}</dt>
                <dd class="col-sm-8">{{ $capabilities['platform'] }}</dd>
            @endif

            @if(!empty($capabilities['connection_type']))
                <dt class="col-sm-4">{{ $profileFields['connection_type'] }}</dt>
                <dd class="col-sm-8">{{ strtoupper($capabilities['connection_type']) }}</dd>
            @endif

            @if(!empty($capabilities['timezone_offset']))
                <dt class="col-sm-4">{{ $profileFields['timezone_offset'] }}</dt>
                <dd class="col-sm-8">
                    {{ $capabilities['timezone_offset'] > 0 ? '+' : '' }}{{ $capabilities['timezone_offset'] }} min
                </dd>
            @endif

            @if($screenSize)
                <dt class="col-sm-4">{{ $profileFields['screen'] }}</dt>
                <dd class="col-sm-8">{{ $screenSize }}</dd>
            @endif

            @if(!empty($capabilities['pixel_ratio']))
                <dt class="col-sm-4">{{ $profileFields['pixel_ratio'] }}</dt>
                <dd class="col-sm-8">{{ $capabilities['pixel_ratio'] }}</dd>
            @endif

            @if(!empty($capabilities['hardware_concurrency']))
                <dt class="col-sm-4">{{ $profileFields['hardware_concurrency'] }}</dt>
                <dd class="col-sm-8">{{ $capabilities['hardware_concurrency'] }}</dd>
            @endif

            @if(isset($capabilities['device_memory']))
                <dt class="col-sm-4">{{ $profileFields['device_memory'] }}</dt>
                <dd class="col-sm-8">{{ $capabilities['device_memory'] }} {{ __('admin.capability_device_memory_unit') }}</dd>
            @endif
        </dl>
    </div>

    <div>
        <h6 class="text-uppercase small text-muted fw-semibold">{{ __('admin.capability_feature_support') }}</h6>
        <div class="d-flex flex-wrap gap-2">
            @foreach($featureFlags as $key => $meta)
                @php
                    $supported = filter_var($capabilities[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $label = $meta['label'];
                @endphp
                <span class="badge {{ $supported ? 'bg-success' : 'bg-secondary' }}"
                      title="{{ $supported ? __('admin.capability_tooltip_supported', ['feature' => $label]) : __('admin.capability_tooltip_missing', ['feature' => $label]) }}">
                    <i class="bi {{ $meta['icon'] }} me-1"></i>
                    {{ $label }}
                </span>
            @endforeach
        </div>
    </div>
@endif

