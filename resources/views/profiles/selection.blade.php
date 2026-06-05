@extends('layouts.frontend')

@section('title', __('gallery.profile_selection') . ' - ' . __('common.app_name'))

@section('header-actions')
<x-ui.pwa-install-button />
<button type="button" class="btn btn-outline-light border-0" title="{{ __('gallery.settings') }}" data-bs-toggle="offcanvas" data-bs-target="#optionsMenuOffcanvas">
    <i class="bi bi-gear fs-3"></i>
</button>
@endsection

@section('main-content')
<div class="row justify-content-center">
    @if(isset($children) && $children->count() > 0)
        @foreach($children as $child)
            <div class="col-auto mb-4 text-center">
                @if($child->hasPin())
                    <button type="button" 
                            class="btn btn-transparent p-0 text-decoration-none d-flex flex-column align-items-center border-0" 
                            data-bs-toggle="modal" 
                            data-bs-target="#pinEntryModal"
                            data-child-username="{{ $child->username }}"
                            data-child-slug="{{ $child->slug }}">
                        <x-ui.user-avatar variant="tile" :user="$child" :showName="true" />
                    </button>
                @else
                    <a href="{{ route('gallery.show', ['slug' => $child->slug]) }}" class="text-decoration-none d-flex flex-column align-items-center">
                        <x-ui.user-avatar variant="tile" :user="$child" :showName="true" />
                    </a>
                @endif
            </div>
        @endforeach
    @else
        <div class="col-12 text-center d-flex flex-column align-items-center justify-content-center" style="min-height: 60vh;">
            <x-ui.user-avatar 
                image="{{ asset('assets/cats/Cat_Adopt_Sticker_by_Pusheen.gif') }}"
                :title="__('gallery.no_profiles_added')"
                variant="normal-lg"
                mb="mb-0"
            />
        </div>
    @endif
</div>
@endsection

{{-- Always render PIN modal on profile-selection since device is always registered here --}}
{{-- Admin password modal is already included in layouts/frontend.blade.php --}}
<x-modals.pin-entry-modal />

@push('scripts')
    @vite('resources/js/resources/pins/entry.js')
@endpush

