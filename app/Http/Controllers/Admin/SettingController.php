<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    public function edit()
    {
        // Check admin access directly
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $apiKey = Setting::where('key', 'youtube_api_key')->first();
        $adminNotificationEmails = Setting::where('key', 'admin_notification_emails')->first();
        $googleCloudProjectId = Setting::where('key', 'google_cloud_project_id')->first();
        $googleCloudServiceAccount = Setting::where('key', 'google_cloud_service_account')->first();
        
        return view('admin.settings.edit', compact(
            'apiKey',
            'adminNotificationEmails',
            'googleCloudProjectId',
            'googleCloudServiceAccount'
        ));
    }

    public function update(Request $request)
    {
        // Check admin access directly
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        $validated = $request->validate([
            'youtube_api_key' => 'required|string',
            'admin_notification_emails' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (!empty($value)) {
                    $emails = array_map('trim', explode(',', $value));
                    foreach ($emails as $email) {
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $fail(__('admin.admin_notification_emails_invalid'));
                            return;
                        }
                    }
                }
            }],
            'google_cloud_project_id' => 'nullable|string',
            'google_cloud_service_account' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if (!empty($value)) {
                    // Validate JSON format
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $fail(__('admin.google_cloud_service_account_invalid_json'));
                        return;
                    }
                    // Validate required fields in service account JSON
                    $requiredFields = ['type', 'project_id', 'private_key', 'client_email'];
                    foreach ($requiredFields as $field) {
                        if (!isset($decoded[$field])) {
                            $fail(__('admin.google_cloud_service_account_missing_field', ['field' => $field]));
                            return;
                        }
                    }
                }
            }],
        ]);

        Setting::updateOrCreate(
            ['key' => 'youtube_api_key'],
            ['value' => $validated['youtube_api_key']]
        );

        Setting::updateOrCreate(
            ['key' => 'admin_notification_emails'],
            ['value' => $validated['admin_notification_emails'] ?? '']
        );

        Setting::updateOrCreate(
            ['key' => 'google_cloud_project_id'],
            ['value' => $validated['google_cloud_project_id'] ?? '']
        );

        Setting::updateOrCreate(
            ['key' => 'google_cloud_service_account'],
            ['value' => $validated['google_cloud_service_account'] ?? '']
        );

        return redirect()->route('admin.settings.edit')
            ->with('success', __('messages.settings_saved'));
    }

    /**
     * Clear all application cache for all users and assets.
     * This includes:
     * - Laravel application cache
     * - View cache (Blade templates)
     * - Route cache
     * - Config cache
     * - Vite manifest cache
     * - All user gallery caches
     * - All API response caches
     * 
     * Also stores asset version timestamp to trigger client-side cache clearing toast.
     */
    public function clearCache()
    {
        // Check admin access directly
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }
        
        try {
            // Clear Laravel application cache
            Cache::flush();
            
            // Clear view cache (Blade templates)
            Artisan::call('view:clear');
            
            // Clear route cache
            Artisan::call('route:clear');
            
            // Clear config cache
            Artisan::call('config:clear');
            
            // Note: Vite manifest is a build artifact, not a cache
            // It should be regenerated with `npm run build`, not cleared here
            // Deleting it would cause ViteManifestNotFoundException errors
            
            // Update cache version for all users to force cache invalidation
            // This ensures all devices see updates immediately
            User::query()->update(['cache_version' => now()]);
            
            // Store asset version timestamp to trigger client-side cache clearing toast
            // This will prompt users to clear their browser/service worker cache
            Setting::updateOrCreate(
                ['key' => 'asset_version'],
                ['value' => (string) now()->timestamp]
            );
            
            return redirect()->route('admin.settings.edit')
                ->with('success', __('admin.cache_cleared_successfully'));
        } catch (\Exception $e) {
            \Log::error('Cache clear failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('admin.settings.edit')
                ->with('error', __('admin.cache_clear_failed'));
        }
    }
}
