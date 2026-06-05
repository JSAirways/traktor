@props([
    'channelId' => 'all',
])

<thead>
    <tr>
        <th style="width: 40px;">
            <input type="checkbox" class="channel-select-all form-check-input" 
                   data-channel-id="{{ $channelId }}" 
                   onchange="toggleSelectAllChannel(this, '{{ $channelId }}')">
        </th>
        <th style="width: 50px;" class="text-center">
            <span class="d-none d-md-inline">{{ __('admin.drag') }}</span>
            <span class="d-md-none">≡</span>
        </th>
        <th class="d-none d-md-table-cell">{{ __('admin.thumbnail') }}</th>
        <th class="d-none d-md-table-cell">{{ __('admin.duration') }}</th>
        <th>{{ __('admin.title') }}</th>
        <th class="text-end">{{ __('admin.visible') }} / {{ __('admin.actions') }}</th>
    </tr>
</thead>

