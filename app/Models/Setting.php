<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function getApiKey(): ?string
    {
        return optional(self::where('key', 'youtube_api_key')->first())->value;
    }

    public static function setApiKey(string $apiKey): void
    {
        self::updateOrCreate(
            ['key' => 'youtube_api_key'],
            ['value' => $apiKey]
        );
    }

    public static function getAdminNotificationEmails(): array
    {
        $setting = self::where('key', 'admin_notification_emails')->first();
        if (!$setting || empty($setting->value)) {
            return [];
        }

        $emails = array_map('trim', explode(',', $setting->value));

        return array_values(array_filter($emails, fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL)));
    }

    /**
     * Get Google Cloud Project ID from settings
     */
    public static function getGoogleCloudProjectId(): ?string
    {
        $setting = self::where('key', 'google_cloud_project_id')->first();
        return $setting ? $setting->value : null;
    }

    /**
     * Get Google Cloud Service Account JSON from settings
     */
    public static function getGoogleCloudServiceAccount(): ?string
    {
        $setting = self::where('key', 'google_cloud_service_account')->first();
        return $setting ? $setting->value : null;
    }

}

