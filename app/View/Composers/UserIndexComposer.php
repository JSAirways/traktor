<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\User;
use Illuminate\View\View;

class UserIndexComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        // Get pending count
        $pendingCount = User::pending()->count();
        
        // Process profile picture paths for each user
        $users = $view->getData()['users'] ?? collect();
        
        // Check if $users is a paginator instance (preserve pagination)
        $isPaginator = $users instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator 
            || $users instanceof \Illuminate\Contracts\Pagination\Paginator;
        
        if ($isPaginator) {
            // For paginators, transform the collection while preserving pagination
            $collection = $users->getCollection()->map(function ($user) {
                return $this->processUser($user);
            });
            $users->setCollection($collection);
            $processedUsers = $users;
        } else {
            // For regular collections, just map
            $processedUsers = $users->map(function ($user) {
                return $this->processUser($user);
            });
        }
        
        $view->with([
            'pendingCount' => $pendingCount,
            'users' => $processedUsers,
        ]);
    }
    
    /**
     * Process a single user to add computed properties.
     */
    private function processUser(User $user): User
    {
        $profilePicturePath = null;
        $isRandom = false;
        
        if ($user->profile_picture) {
            $category = $user->profile_picture_category ?? 'cats';
            $profilePicturePath = asset('assets/profile-pictures/' . $category . '/' . $user->profile_picture);
        } elseif ($user->cat_gif) {
            $profilePicturePath = asset('assets/profile-pictures/cats/' . $user->cat_gif);
        } else {
            $isRandom = true;
        }
        
        // Add computed properties to user object
        $user->profile_picture_path = $profilePicturePath;
        $user->is_random_profile_picture = $isRandom;
        
        // Process status colors
        $statusColors = [
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger'
        ];
        
        if ($user->account_status) {
            $user->status_color = $statusColors[$user->account_status] ?? 'secondary';
        }
        
        // Add PIN information (only for parent accounts)
        if ($user->parent_id === null) {
            $user->has_pin = $user->hasPin();
            $user->pin_value = $user->hasPin() ? $user->getViewPin() : null;
        } else {
            $user->has_pin = false;
            $user->pin_value = null;
        }
        
        return $user;
    }
}

