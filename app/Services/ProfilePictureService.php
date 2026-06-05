<?php

namespace App\Services;

class ProfilePictureService
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = public_path('assets/profile-pictures');
    }

    public function getCategories(): array
    {
        if (!is_dir($this->basePath)) {
            return [];
        }

        return collect(scandir($this->basePath))
            ->reject(fn ($dir) => in_array($dir, ['.', '..'], true))
            ->filter(fn ($dir) => is_dir($this->basePath . DIRECTORY_SEPARATOR . $dir))
            ->values()
            ->all();
    }

    public function getPicturesByCategory(string $category): array
    {
        $categoryPath = $this->basePath . DIRECTORY_SEPARATOR . $category;

        if (!is_dir($categoryPath)) {
            return [];
        }

        return collect(scandir($categoryPath))
            ->reject(fn ($file) => in_array($file, ['.', '..'], true))
            ->filter(function ($file) use ($categoryPath) {
                if (is_dir($categoryPath . DIRECTORY_SEPARATOR . $file)) {
                    return false;
                }

                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                return in_array($extension, ['gif', 'jpg', 'jpeg', 'png', 'webp'], true);
            })
            ->values()
            ->all();
    }

    public function getAllPicturesByCategory(): array
    {
        $result = [];

        foreach ($this->getCategories() as $category) {
            $result[$category] = $this->getPicturesByCategory($category);
        }

        return $result;
    }

    public function validatePicture(string $filename, string $category): bool
    {
        $categoryPath = $this->basePath . DIRECTORY_SEPARATOR . $category;

        if (!is_dir($categoryPath)) {
            return false;
        }

        $filePath = $categoryPath . DIRECTORY_SEPARATOR . $filename;

        return file_exists($filePath) && is_file($filePath);
    }

    public function getAssetPath(string $filename, string $category): string
    {
        return "assets/profile-pictures/{$category}/{$filename}";
    }

    public function getRandomPicture(string $category): ?string
    {
        $pictures = $this->getPicturesByCategory($category);

        if (empty($pictures)) {
            return null;
        }

        return $pictures[array_rand($pictures)];
    }

    public function getRandomPictureFromAnyCategory(): ?array
    {
        $categories = $this->getCategories();

        if (empty($categories)) {
            return null;
        }

        $category = $categories[array_rand($categories)];
        $picture = $this->getRandomPicture($category);

        if (!$picture) {
            return null;
        }

        return [
            'filename' => $picture,
            'category' => $category,
        ];
    }
}


