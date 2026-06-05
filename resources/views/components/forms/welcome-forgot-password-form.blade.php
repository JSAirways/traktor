@props([])

<div id="forgotPasswordView" class="col-12 col-md-6 col-lg-4" style="display: none;">
    <div class="card bg-dark border-success text-light">
        <div class="card-body p-4">
            <h5 class="text-center text-light mb-4 mt-2">Reset Password</h5>
            <p class="text-center text-muted mb-4">Enter your email address and we'll send you a password reset link.</p>
            
            <x-ui.flash-messages />

            <form method="POST" action="{{ route('password.email') }}" id="forgotPasswordForm">
                @csrf
                
                <x-forms.form-field 
                    name="email" 
                    label="Email" 
                    type="email"
                    :required="true"
                    :value="old('email')"
                    :autofocus="true"
                />

                <div class="d-flex flex-column gap-2">
                    <button type="submit" class="btn btn-success w-100">Send Password Reset Link</button>
                </div>
            </form>
        </div>
    </div>
</div>

