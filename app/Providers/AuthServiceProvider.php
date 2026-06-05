<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Video::class => \App\Policies\ContentPolicy::class,
        \App\Models\Playlist::class => \App\Policies\ContentPolicy::class,
        \App\Models\Setting::class => \App\Policies\SettingPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Define admin gate
        Gate::define('admin', function ($user) {
            return $user->isAdmin();
        });
    }
}
