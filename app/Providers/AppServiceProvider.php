<?php

namespace App\Providers;

use App\View\Composers\AppComposer;
use App\View\Composers\DeviceComposer;
use App\View\Composers\GalleryComposer;
use App\View\Composers\PlayerComposer;
use App\View\Composers\UserIndexComposer;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share device information with frontend and app layouts
        View::composer('layouts.frontend', DeviceComposer::class);
        View::composer('layouts.app', DeviceComposer::class);
        View::composer('*', AppComposer::class);
        
        // Register specific view composers
        View::composer('player.show', PlayerComposer::class);
        View::composer('player._partial', PlayerComposer::class);
        View::composer('galleries.show', GalleryComposer::class);
        View::composer('admin.users.index', UserIndexComposer::class);
        
        // Register response macros for consistent API responses
        $this->registerResponseMacros();
        
        // Register custom Blade directives
        $this->registerBladeDirectives();
    }
    
    /**
     * Register response macros for consistent API responses.
     */
    protected function registerResponseMacros(): void
    {
        Response::macro('success', function ($first = null, $second = null, $status = 200) {
            $response = ['success' => true];
            
            // Determine which parameter is message and which is data
            // If first is a string and second is array/null, first is message
            // If first is array/object and second is string/null, first is data
            // If only one parameter, it's data
            $message = null;
            $data = null;
            
            if ($first !== null && $second !== null) {
                // Two parameters: determine order by type
                if (is_string($first) && (is_array($second) || is_object($second) || $second === null)) {
                    // Pattern: success(message, data)
                    $message = $first;
                    $data = $second;
                } elseif ((is_array($first) || is_object($first)) && (is_string($second) || $second === null)) {
                    // Pattern: success(data, message)
                    $data = $first;
                    $message = $second;
                } else {
                    // Fallback: treat first as data, second as message
                    $data = $first;
                    $message = $second;
                }
            } elseif ($first !== null) {
                // Single parameter: treat as data
                $data = $first;
            }
            
            if ($message !== null) {
                $response['message'] = $message;
            }
            
            if ($data !== null) {
                $response['data'] = $data;
            }
            
            return response()->json($response, $status);
        });
        
        Response::macro('error', function ($message, $errors = null, $status = 400) {
            $response = [
                'success' => false,
                'message' => $message,
            ];
            
            if ($errors !== null) {
                $response['errors'] = $errors;
            }
            
            return response()->json($response, $status);
        });
    }
    
    /**
     * Register custom Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // @canManage($user) - Check if current user can manage the given user
        Blade::if('canManage', function ($user) {
            return auth()->check() && auth()->user()->canManage($user);
        });
        
        // @isAdmin - Check if current user is admin
        Blade::if('isAdmin', function () {
            return auth()->check() && auth()->user()->isAdmin();
        });
        
        // @isParent - Check if current user is a parent (has no parent_id)
        Blade::if('isParent', function () {
            return auth()->check() && auth()->user()->parent_id === null;
        });
        
        // @isChild - Check if current user is a child (has parent_id)
        Blade::if('isChild', function () {
            return auth()->check() && auth()->user()->parent_id !== null;
        });
    }
}
