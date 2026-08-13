<?php

namespace App\Http\Requests;

use App\Constants\DeviceConstants;
use Illuminate\Foundation\Http\FormRequest;

class DeviceRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        // Check if this is a password-only login
        $isPasswordOnlyLogin = trim($this->input('device_name', '')) === DeviceConstants::PASSWORD_ONLY_LOGIN_FLAG;
        
        if ($isPasswordOnlyLogin) {
            // Password-only login - only validate password (email is passed as hidden field)
            return [
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
                'device_uid' => ['nullable', 'uuid'],
                'user_agent' => ['nullable', 'string'],
                'screen_resolution' => ['nullable', 'string', 'max:50'],
                'capabilities' => ['nullable', 'string'],
            ];
        }
        
        // Full registration - validate all fields including device_name
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'device_uid' => ['nullable', 'uuid'],
            'user_agent' => ['nullable', 'string'],
            'screen_resolution' => ['nullable', 'string', 'max:50'],
            'capabilities' => ['nullable', 'string'],
        ];
    }
}

