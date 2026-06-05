@props([
    'channelId' => null, // Channel ID for channel-scoped toolbar
    'hasContent' => false, // Whether this channel has content
])

@if($hasContent)
@php
    $toolbarId = $channelId ? "bulkActionsToolbar-{$channelId}" : 'bulkActionsToolbar';
    $selectedCountId = $channelId ? "selectedCount-{$channelId}" : 'selectedCount';
@endphp
<div id="{{ $toolbarId }}" class="mb-3 px-3" style="background: #f8f9fa; padding: 12px; border-radius: 4px; display: none;">
    <div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center gap-2 gap-md-3">
        <span id="{{ $selectedCountId }}" class="fw-bold">0 {{ __('common.items_selected') }}</span>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-success btn-sm" onclick="bulkAction('show', '{{ $channelId }}')">
                <i class="bi bi-eye"></i> <span class="d-none d-sm-inline">{{ __('common.show_selected') }}</span>
            </button>
            <button type="button" class="btn btn-warning btn-sm" onclick="bulkAction('hide', '{{ $channelId }}')">
                <i class="bi bi-eye-slash"></i> <span class="d-none d-sm-inline">{{ __('common.hide_selected') }}</span>
            </button>
            <button type="button" class="btn btn-danger btn-sm" onclick="bulkAction('delete', '{{ $channelId }}')">
                <i class="bi bi-trash"></i> <span class="d-none d-sm-inline">{{ __('common.delete_selected') }}</span>
            </button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection('{{ $channelId }}')">
                <i class="bi bi-x"></i> <span class="d-none d-sm-inline">{{ __('common.clear') }}</span>
            </button>
        </div>
    </div>
</div>
@endif

