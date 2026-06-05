<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\View\View;

class GalleryComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $data = $view->getData();
        
        // Get cache version timestamp (handle both Carbon instance and string/null cases)
        $cacheVersion = 0;
        if (isset($data['user']) && $data['user']->cache_version) {
            $cacheVersion = $data['user']->getCacheVersionTimestamp();
        }
        
        $view->with([
            'cacheVersion' => $cacheVersion,
        ]);
    }
}

