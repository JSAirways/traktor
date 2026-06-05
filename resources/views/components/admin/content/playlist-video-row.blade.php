@props([
    'video' => null,
    'isMobile' => false,
])

@if($isMobile)
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center py-2 border-bottom gap-2">
        <div class="d-flex align-items-center gap-2 flex-grow-1">
            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}"
                class="rounded flex-shrink-0 d-md-none" style="width: 40px; height: 30px; object-fit: cover;">
            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}"
                class="rounded flex-shrink-0 d-none d-md-inline-block" style="width: 60px; height: 45px; object-fit: cover;">
            <div class="d-flex flex-column flex-grow-1">
                <span class="text-muted" style="font-size: 0.75rem;">{{ gmdate('H:i:s', $video->duration) }}</span>
                <span class="small">{{ $video->title }}</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <form method="POST" action="{{ route('admin.content.toggle-video-visibility') }}" class="d-inline">
                @csrf
                <input type="hidden" name="video_id" value="{{ $video->id }}">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" {{ $video->is_visible ? 'checked' : '' }}
                        onchange="this.form.submit()">
                </div>
            </form>
            <form method="POST" action="{{ route('admin.content.delete') }}" class="d-inline"
                onsubmit="return confirm('{{ __('admin.confirm_delete_video') }}')">
                @csrf
                <input type="hidden" name="id" value="{{ $video->id }}">
                <input type="hidden" name="type" value="video">
                <button type="submit" class="btn btn-outline-danger border-0" title="{{ __('common.delete') }}">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </div>
@else
    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}"
                class="rounded d-none d-md-inline-block" style="width: 60px; height: 45px; object-fit: cover;">
            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}"
                class="rounded d-md-none" style="width: 40px; height: 30px; object-fit: cover;">
            <span class="text-muted small">{{ gmdate('H:i:s', $video->duration) }}</span>
            <span>{{ $video->title }}</span>
        </div>
        <div class="d-flex align-items-center">
            <form method="POST" action="{{ route('admin.content.toggle-video-visibility') }}" class="d-inline">
                @csrf
                <input type="hidden" name="video_id" value="{{ $video->id }}">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" {{ $video->is_visible ? 'checked' : '' }}
                        onchange="this.form.submit()">
                </div>
            </form>
            <form method="POST" action="{{ route('admin.content.delete') }}" class="d-inline"
                onsubmit="return confirm('{{ __('admin.confirm_delete_video') }}')">
                @csrf
                <input type="hidden" name="id" value="{{ $video->id }}">
                <input type="hidden" name="type" value="video">
                <button type="submit" class="btn btn-outline-danger border-0" title="{{ __('common.delete') }}">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </div>
@endif

