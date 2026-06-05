<?php

namespace App\Http\Controllers\Concerns;

trait HandlesProfilePicture
{
    /**
     * Map cat_gif form input to profile_picture and category.
     * 
     * @param string|null $catGif The cat_gif value from form
     * @param string $category The category (default: 'cats')
     * @return array Array with 'profile_picture' and 'profile_picture_category' keys
     */
    protected function mapProfilePicture(?string $catGif, string $category = 'cats'): array
    {
        return [
            'profile_picture' => $catGif,
            'profile_picture_category' => $catGif ? $category : null,
        ];
    }
}

