<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\GoogleCloudMonitoringService;
use Illuminate\Http\JsonResponse;

class QuotaController extends Controller
{
    /**
     * Get YouTube API quota statistics
     */
    public function getQuotaStats(): JsonResponse
    {
        // Check admin access directly
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->error(__('messages.unauthorized_access'), null, 403);
        }

        try {
            // Check if Google Cloud Monitoring package is available
            if (!class_exists(\Google\Cloud\Monitoring\V3\Client\MetricServiceClient::class)) {
                return response()->error(
                    __('admin.youtube_quota_package_not_installed'),
                    null,
                    400
                );
            }

            // Get Google Cloud credentials from settings
            $projectId = Setting::getGoogleCloudProjectId();
            $serviceAccount = Setting::getGoogleCloudServiceAccount();

            // Check if credentials are configured
            if (empty($projectId) || empty($serviceAccount)) {
                return response()->error(
                    __('admin.youtube_quota_not_configured'),
                    null,
                    400
                );
            }

            // Initialize monitoring service
            $monitoringService = new GoogleCloudMonitoringService($projectId, $serviceAccount);

            // Fetch quota usage
            $quotaData = $monitoringService->getYouTubeQuotaUsage();

            return response()->success($quotaData);

        } catch (\Exception $e) {
            \Log::error('Failed to fetch YouTube quota stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Check for specific error types
            $errorMessage = $e->getMessage();
            $statusCode = 500;
            
            // Check for permission denied errors (handle both plain text and JSON-encoded errors)
            $errorMessageLower = strtolower($errorMessage);
            if (strpos($errorMessageLower, 'permission_denied') !== false || 
                strpos($errorMessageLower, 'permission denied') !== false ||
                strpos($errorMessageLower, 'code\": 7') !== false) {
                return response()->error(
                    __('admin.youtube_quota_permission_denied'),
                    ['details' => __('admin.youtube_quota_permission_denied_help')],
                    403
                );
            }
            
            // Check for credentials not configured
            if (strpos($errorMessage, 'credentials not configured') !== false) {
                return response()->error(
                    __('admin.youtube_quota_not_configured'),
                    null,
                    400
                );
            }

            return response()->error(
                __('admin.youtube_quota_error'),
                ['details' => $errorMessage],
                $statusCode
            );
        }
    }

    /**
     * Clear quota cache (force refresh)
     */
    public function clearQuotaCache(): JsonResponse
    {
        // Check admin access directly
        if (!auth()->user()->isAdmin()) {
            abort(403, 'This action is unauthorized.');
        }

        try {
            // Check if Google Cloud Monitoring package is available
            if (!class_exists(\Google\Cloud\Monitoring\V3\Client\MetricServiceClient::class)) {
                return response()->error(
                    __('admin.youtube_quota_package_not_installed'),
                    null,
                    400
                );
            }

            $projectId = Setting::getGoogleCloudProjectId();
            $serviceAccount = Setting::getGoogleCloudServiceAccount();

            if (empty($projectId) || empty($serviceAccount)) {
                return response()->error(
                    __('admin.youtube_quota_not_configured'),
                    null,
                    400
                );
            }

            $monitoringService = new GoogleCloudMonitoringService($projectId, $serviceAccount);
            $monitoringService->clearCache();

            return response()->success(null, __('admin.youtube_quota_cache_cleared'));

        } catch (\Exception $e) {
            return response()->error(
                __('admin.youtube_quota_error'),
                ['details' => $e->getMessage()],
                500
            );
        }
    }
}

