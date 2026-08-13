<?php

namespace App\Http\Controllers\Concerns;

use App\Services\ProfilePictureService;

trait HandlesProfilePicture
{
    /**
     * Map cat_gif form input to profile_picture and category.
     * Empty / shuffle selection assigns and persists a random picture.
     *
     * @param string|null $catGif The cat_gif value from form
     * @param string $category The category (default: 'cats')
     * @return array Array with 'profile_picture' and 'profile_picture_category' keys
     */
    protected function mapProfilePicture(?string $catGif, string $category = 'cats'): array
    {
        $catGif = $catGif !== null ? trim($catGif) : null;

        if ($catGif === null || $catGif === '') {
            $catGif = app(ProfilePictureService::class)->getRandomPicture($category);
        }

        return [
            'profile_picture' => $catGif,
            'profile_picture_category' => $catGif ? $category : null,
        ];
    }
}
