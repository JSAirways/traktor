@props(['username' => null, 'deviceName' => null])

@php
    // Show form if there are validation errors for password or username
    $showForm = $errors->has('password') || $errors->has('username') || old('username');
@endphp

<div id="passwordFormView" class="col-12 col-md-6 col-lg-4" style="display: {{ $showForm ? 'block' : 'none' }};">
    <div class="card bg-dark border-success text-light">
        <div class="card-body p-4">
            <x-ui.user-avatar 
                variant="profile"
                containerId="passwordFormProfilePicture"
            />
            <h5 class="text-center text-light mb-4 mt-2" id="passwordFormUsernameDisplay">
                @if($username && $deviceName)
                    {{ $deviceName }} ({{ $username }})
                @elseif($username)
                    {{ $username }}
                @endif
            </h5>

            <form method="POST" action="{{ route('device.register') }}" id="passwordOnlyForm">
                @csrf
                <input type="hidden" name="username" id="passwordFormUsername" value="{{ $username }}">
                <input type="hidden" name="device_name" id="passwordFormDeviceName" value="{{ $deviceName ?? 'Unnamed Device' }}">
                <input type="hidden" name="device_fingerprint" id="passwordFormFingerprint">
                <input type="hidden" name="user_agent" id="passwordFormUserAgent">
                <input type="hidden" name="screen_resolution" id="passwordFormScreenResolution">
                <input type="hidden" name="capabilities" id="passwordFormCapabilities">
                
                <div class="form-floating mb-4">
                    <input type="password" class="form-control @error('password') is-invalid @enderror" 
                           id="passwordFormPassword" name="password" placeholder=" " required autofocus>
                    <label for="passwordFormPassword">Password</label>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-success w-100">Login</button>
                    <a href="{{ route('password.request') }}" class="btn btn-link text-light p-0 text-decoration-none text-center" onclick="storePasswordFormState()">Forgot password</a>
                </div>
            </form>
        </div>
    </div>
</div>

