<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AssetService
{
    public function getCatGifsFromAssets(): array
    {
        return Cache::remember('cat_gifs_list', 3600, function () {
            $gifs = [];
            $catsPath = public_path('assets/cats');

            if (!is_dir($catsPath)) {
                return $gifs;
            }

            foreach (scandir($catsPath) as $file) {
                if ($file === '.' || $file === '..' || is_dir($catsPath . DIRECTORY_SEPARATOR . $file)) {
                    continue;
                }

                if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'gif') {
                    $gifs[] = $file;
                }
            }

            return $gifs;
        });
    }
}


