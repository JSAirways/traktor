@extends('layouts.frontend')

@section('title', __('common.app_name'))

@section('main-content')
    <div class="row justify-content-center">
        <!-- Loading Spinner -->
        <div id="loadingView" class="col-12 text-center {{ ($errors->has('password') || $errors->has('username')) && old('username') ? 'd-none' : 'd-block' }}">
            <x-ui.loading-spinner :text="__('welcome.checking_device')" />
        </div>

        <!-- User Selection Grid -->
        <div id="userSelectionView" class="col-12 d-none">
            <x-welcome.welcome-user-selection />
            <x-welcome.user-tile-template />
            <x-ui.toast-notification-template />
        </div>
    </div>
        
<!-- Configuration data for JavaScript -->
<script type="application/json" data-welcome-config>
{
    "catGifs": @json($catGifs ?? []),
    "registeredUsersRoute": "{{ route('api.device.registered-users') }}",
    "fingerprintApiRoute": "{{ route('api.device.generate-fingerprint') }}",
    "catGifBasePath": "{{ asset('assets/profile-pictures/cats') }}/",
    "csrfToken": "{{ csrf_token() }}",
    "hasPasswordError": @json($errors->has('password') && old('username')),
    "oldUsername": @json(old('username')),
    "oldDeviceName": @json(old('device_name')),
    "duplicateDeviceError": @json(session('device_duplicate_error'))
}
</script>

@push('scripts')
    @vite('resources/js/resources/welcome/index.js')
@endpush
@endsection

