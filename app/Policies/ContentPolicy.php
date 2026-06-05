<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ContentPolicy
{
    /**
     * Determine if user can manage content owned by another user.
     */
    protected function canManageContent(User $user, Model $content): bool
    {
        return $user->isAdmin() || 
               $content->user_id === $user->id || 
               $user->canManage($content->user);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Users can view content, but scope is handled in resource
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Model $content): bool
    {
        return $this->canManageContent($user, $content);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create content
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Model $content): bool
    {
        return $this->canManageContent($user, $content);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Model $content): bool
    {
        return $this->canManageContent($user, $content);
    }
}

