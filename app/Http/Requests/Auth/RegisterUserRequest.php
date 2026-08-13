<?php

namespace App\Http\Requests\Auth;

use App\Services\ProfilePictureService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim((string) $this->input('email'))),
            ]);
        }

        if ($this->has('username')) {
            $this->merge([
                'username' => trim(strip_tags((string) $this->input('username'))),
            ]);
        }

        if ($this->has('how_heard_about')) {
            $this->merge([
                'how_heard_about' => trim(strip_tags((string) $this->input('how_heard_about'))),
            ]);
        }

        if ($this->has('cat_gif')) {
            $catGif = trim(strip_tags((string) $this->input('cat_gif')));
            $this->merge([
                'cat_gif' => $catGif === '' ? null : $catGif,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'how_heard_about' => ['required', 'string', 'min:2', 'max:500'],
            'locale' => ['nullable', 'string', 'in:' . implode(',', config('app.supported_locales', ['en']))],
            'cat_gif' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $catGif = $this->input('cat_gif');

            if ($catGif === null || $catGif === '') {
                return;
            }

            // Reject path traversal / unexpected filenames
            if ($catGif !== basename($catGif) || str_contains($catGif, '..')) {
                $validator->errors()->add('cat_gif', __('validation.exists', ['attribute' => 'cat_gif']));
                return;
            }

            $service = app(ProfilePictureService::class);
            if (!$service->validatePicture($catGif, 'cats')) {
                $validator->errors()->add('cat_gif', __('validation.exists', ['attribute' => 'cat_gif']));
            }
        });
    }

    public function messages(): array
    {
        return [
            'email.required' => __('messages.email_required'),
            'email.email' => __('messages.email_invalid'),
            'email.unique' => __('messages.email_taken'),
            'username.required' => __('messages.username_required'),
            'password.required' => __('messages.password_required'),
            'password.confirmed' => __('messages.password_confirmation_mismatch'),
            'how_heard_about.required' => __('messages.how_heard_about_required'),
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => __('common.username'),
            'email' => __('common.email'),
            'password' => __('common.password'),
            'password_confirmation' => __('common.confirm_password'),
            'how_heard_about' => __('forms.how_heard_about'),
            'cat_gif' => __('common.profile_picture'),
        ];
    }
}
