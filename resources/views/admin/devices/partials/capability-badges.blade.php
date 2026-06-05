@php
    $capabilities = $capabilities ?? [];
    $hasCapabilities = is_array($capabilities) && count($capabilities) > 0;

    $badgeConfigs = [
        'pwa' => [
            'show' => isset($capabilities['has_service_worker']) || isset($capabilities['has_indexed_db']),
            'enabled' => ($capabilities['has_service_worker'] ?? false) && ($capabilities['has_indexed_db'] ?? false),
            'label' => __('admin.capability_badge_pwa'),
            'icon' => 'bi-cloud-check',
            'class' => 'bg-success',
        ],
        'touch' => [
            'show' => array_key_exists('touch_support', $capabilities),
            'enabled' => filter_var($capabilities['touch_support'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'label' => __('admin.capability_badge_touch'),
            'icon' => 'bi-hand-index-thumb',
            'class' => 'bg-success',
        ],
        'autoplay' => [
            'show' => array_key_exists('has_autoplay_inline', $capabilities),
            'enabled' => filter_var($capabilities['has_autoplay_inline'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'label' => __('admin.capability_badge_autoplay'),
            'icon' => 'bi-play-btn',
            'class' => 'bg-success',
        ],
        'storage' => [
            'show' => (isset($capabilities['has_local_storage']) || isset($capabilities['has_session_storage'])),
            'enabled' => ($capabilities['has_local_storage'] ?? false) && ($capabilities['has_session_storage'] ?? false),
            'label' => __('admin.capability_badge_storage'),
            'icon' => 'bi-hdd-stack',
            'class' => 'bg-success',
        ],
        'motion' => [
            'show' => filter_var($capabilities['prefers_reduced_motion'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'enabled' => true,
            'label' => __('admin.capability_badge_motion'),
            'icon' => 'bi-arrow-repeat',
            'class' => 'bg-info text-dark',
        ],
        'dark' => [
            'show' => filter_var($capabilities['prefers_dark_mode'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'enabled' => true,
            'label' => __('admin.capability_badge_dark'),
            'icon' => 'bi-moon-stars',
            'class' => 'bg-info text-dark',
        ],
    ];
@endphp

@if(!$hasCapabilities)
    <span class="badge bg-secondary">{{ __('admin.capabilities_not_reported_short') }}</span>
@else
    <div class="d-flex flex-wrap gap-1">
        @foreach($badgeConfigs as $config)
            @if($config['show'])
                <span class="badge {{ $config['enabled'] ? $config['class'] : 'bg-secondary' }}" title="{{ $config['label'] }}">
                    <i class="bi {{ $config['icon'] }} me-1"></i>{{ $config['label'] }}
                </span>
            @endif
        @endforeach
    </div>
@endif


