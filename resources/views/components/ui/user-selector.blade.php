@props([
    'users',                          // Collection|array of User models or selector arrays
    'selected' => null,               // Currently selected User model, array, or null
    'route',                          // Base URL/route for navigation
    'param' => 'user_id',             // Query parameter name
    'valueKey' => 'id',               // Field used as the option value: id|slug
    'allLabel' => null,               // Optional "All" option label (empty param value)
    'ariaLabel' => null,              // Accessible label prefix
    'id' => 'adminUserSelector',
])

@php
    $authId = auth()->id();

    $normalize = function ($user) use ($authId, $valueKey) {
        if ($user instanceof \App\Models\User) {
            return [
                'value' => (string) $user->{$valueKey},
                'username' => $user->username,
                'avatar_url' => $user->profilePictureUrl(),
                'is_you' => $user->id === $authId,
            ];
        }

        $value = $user[$valueKey] ?? $user['value'] ?? null;

        return [
            'value' => $value !== null ? (string) $value : '',
            'username' => $user['username'] ?? '',
            'avatar_url' => $user['avatar_url'] ?? null,
            'is_you' => (bool) ($user['is_you'] ?? (($user['id'] ?? null) === $authId)),
        ];
    };

    $options = collect($users)->map($normalize)->filter(fn ($u) => $u['value'] !== '' && $u['username'] !== '')->values();

    $selectedValue = null;
    $selectedUsername = null;
    $selectedAvatar = null;

    if ($selected instanceof \App\Models\User) {
        $selectedValue = (string) $selected->{$valueKey};
        $selectedUsername = $selected->username;
        $selectedAvatar = $selected->profilePictureUrl();
    } elseif (is_array($selected)) {
        $normalizedSelected = $normalize($selected);
        $selectedValue = $normalizedSelected['value'] ?: null;
        $selectedUsername = $normalizedSelected['username'] ?: null;
        $selectedAvatar = $normalizedSelected['avatar_url'];
    }

    $showingAll = $allLabel && ($selectedValue === null || $selectedValue === '');
    $displayName = $showingAll ? $allLabel : ($selectedUsername ?: __('welcome.select_user'));
    $displayAvatar = $showingAll ? null : $selectedAvatar;
    $canSwitch = $options->count() > 1 || ($allLabel && $options->count() > 0);
    $labelPrefix = $ariaLabel ?: __('admin.viewing_analytics_for');

    $optionUrl = function (?string $value) use ($route, $param) {
        $query = [];
        if ($value !== null && $value !== '') {
            $query[$param] = $value;
        }

        return $query === [] ? $route : $route . (str_contains($route, '?') ? '&' : '?') . http_build_query($query);
    };
@endphp

@if($canSwitch)
    <div class="dropdown admin-user-selector">
        <button
            class="btn admin-user-selector-toggle dropdown-toggle"
            type="button"
            id="{{ $id }}"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-label="{{ $labelPrefix }} {{ $displayName }}"
        >
            <span class="admin-user-avatar">
                @if($displayAvatar)
                    <img src="{{ $displayAvatar }}" alt="" class="admin-user-avatar-img">
                @elseif($showingAll)
                    <span class="admin-user-avatar-fallback"><i class="bi bi-people"></i></span>
                @else
                    <span class="admin-user-avatar-fallback">{{ strtoupper(substr($displayName, 0, 1)) }}</span>
                @endif
            </span>
            <span class="admin-user-name">{{ $displayName }}</span>
        </button>
        <ul class="dropdown-menu admin-user-selector-menu" aria-labelledby="{{ $id }}">
            @if($allLabel)
                <li>
                    <a
                        class="dropdown-item admin-user-selector-item {{ $showingAll ? 'active' : '' }}"
                        href="{{ $optionUrl(null) }}"
                    >
                        <span class="admin-user-avatar">
                            <span class="admin-user-avatar-fallback"><i class="bi bi-people"></i></span>
                        </span>
                        <span class="admin-user-name">{{ $allLabel }}</span>
                    </a>
                </li>
            @endif
            @foreach($options as $option)
                <li>
                    <a
                        class="dropdown-item admin-user-selector-item {{ !$showingAll && $selectedValue === $option['value'] ? 'active' : '' }}"
                        href="{{ $optionUrl($option['value']) }}"
                    >
                        <span class="admin-user-avatar">
                            @if($option['avatar_url'])
                                <img src="{{ $option['avatar_url'] }}" alt="" class="admin-user-avatar-img">
                            @else
                                <span class="admin-user-avatar-fallback">{{ strtoupper(substr($option['username'], 0, 1)) }}</span>
                            @endif
                        </span>
                        <span class="admin-user-name">{{ $option['username'] }}</span>
                        @if($option['is_you'])
                            <span class="badge text-bg-light border ms-auto">{{ __('common.you') }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@else
    <div class="admin-user-selector admin-user-selector-static" aria-label="{{ $labelPrefix }} {{ $displayName }}">
        <span class="admin-user-avatar">
            @if($displayAvatar)
                <img src="{{ $displayAvatar }}" alt="" class="admin-user-avatar-img">
            @else
                <span class="admin-user-avatar-fallback">{{ strtoupper(substr($displayName, 0, 1)) }}</span>
            @endif
        </span>
        <span class="admin-user-name">{{ $displayName }}</span>
    </div>
@endif
