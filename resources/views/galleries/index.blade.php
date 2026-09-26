@extends('layouts.app')

@section('title', $user->profile_name . "'s Traktor")
@section('body-class', 'bg-dark text-light view-gallery')

@section('content')
<x-layout.navbar-gallery :profileName="$user->profile_name" :user="$user" />

<!-- Loading Spinner -->
<div id="loadingSpinner" class="loading-spinner-overlay">
    <x-ui.loading-spinner />
</div>

{{-- Gallery View --}}
@php
    $cacheVersion = $user->getCacheVersionTimestamp();
@endphp
<x-gallery.view 
    :user="$user"
    :content="$content ?? []"
    :channels="$channels ?? []"
    :selectedChannel="$selectedChannel ?? null"
    :selectedChannelId="$selectedChannelId ?? 'all'"
    :contentType="$contentType ?? 'all'"
/>

<script 
    data-slug="{{ $user->slug }}" 
    data-channels='{{ json_encode($channels ?? []) }}' 
    data-profile-name="{{ $user->profile_name }}" 
    data-cache-version="{{ $cacheVersion }}"
></script>

@push('scripts')
    @vite('resources/js/resources/galleries/index.js')
@endpush
@endsection

