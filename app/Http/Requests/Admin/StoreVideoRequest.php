<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $targetUserId = $this->get('user_id', $user->id);
        
        if ($user->isAdmin()) {
            return true;
        }
        
        if ($user->id === $targetUserId) {
            return true;
        }
        
        // Check if it's one of their children
        $targetUser = \App\Models\User::find($targetUserId);
        return $targetUser && $user->canManage($targetUser);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Accept either a full YouTube URL or a bare video ID.
            // URL format validation is handled by YouTubeService::extractVideoId().
            'url' => ['required', 'string'],
            'is_playlist' => ['sometimes', 'boolean'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'url.required' => __('messages.url_required'),
            'user_id.exists' => __('messages.user_not_found'),
        ];
    }
}

