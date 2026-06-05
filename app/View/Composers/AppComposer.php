<?php

namespace App\View\Composers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AppComposer
{
    /**
     * Bind data to the view.
     */
    public function compose(View $view): void
    {
        $view->with([
            'currentUser' => Auth::user(),
            'appLocale' => app()->getLocale(),
            'translations' => $this->getTranslations(),
        ]);
    }

    /**
     * Get translations for JavaScript.
     */
    protected function getTranslations(): array
    {
        return [
            'common' => __('common'),
            'auth' => __('auth'),
            'messages' => __('messages'),
            'welcome' => __('welcome'),
            'admin' => __('admin'),
            'gallery' => __('gallery'),
            'forms' => __('forms'),
            'account' => __('account'),
        ];
    }
}

