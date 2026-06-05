<?php

namespace App\Services;

use Exception;
use Google\Cloud\Monitoring\V3\Client\MetricServiceClient;
use Google\Cloud\Monitoring\V3\TimeInterval;
use Google\Cloud\Monitoring\V3\ListTimeSeriesRequest;
use Google\Protobuf\Timestamp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoogleCloudMonitoringService
{
    protected ?string $projectId;
    protected ?string $serviceAccountJson;

    public function __construct(?string $projectId = null, ?string $serviceAccountJson = null)
    {
        // Check if Google Cloud Monitoring package is installed
        if (!class_exists(\Google\Cloud\Monitoring\V3\Client\MetricServiceClient::class)) {
            throw new Exception("Google Cloud Monitoring package is not installed. Please run: composer require google/cloud-monitoring");
        }

        $this->projectId = $projectId;
        $this->serviceAccountJson = $serviceAccountJson;
    }

    /**
     * Get YouTube API quota usage for the current day
     * Returns array with: used, limit, remaining, percentage, timestamp
     */
    public function getYouTubeQuotaUsage(): array
    {
        // Check if credentials are configured
        if (empty($this->projectId) || empty($this->serviceAccountJson)) {
            throw new Exception("Google Cloud credentials not configured");
        }

        // Cache for 5 minutes to avoid excessive API calls
        $cacheKey = "youtube_quota_usage_{$this->projectId}";
        
        return Cache::remember($cacheKey, 300, function () {
            try {
                // Parse service account JSON
                $credentials = json_decode($this->serviceAccountJson, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid service account JSON");
                }

                // Create temporary file for credentials (Google Cloud client requires file path)
                $tempFile = tempnam(sys_get_temp_dir(), 'gcloud_');
                file_put_contents($tempFile, $this->serviceAccountJson);

                // Initialize Metric Service Client
                $client = new MetricServiceClient([
                    'credentials' => $tempFile,
                ]);

                // Set up time interval for today (last 24 hours)
                $endTime = time();
                $startTime = strtotime('today 00:00:00');

                $interval = new TimeInterval();
                $startTimestamp = new Timestamp();
                $startTimestamp->setSeconds($startTime);
                $interval->setStartTime($startTimestamp);
                
                $endTimestamp = new Timestamp();
                $endTimestamp->setSeconds($endTime);
                $interval->setEndTime($endTimestamp);

                // Build the project name
                $projectName = "projects/{$this->projectId}";

                // Query for YouTube API request count
                // Filter for youtube.googleapis.com service
                $filter = 'metric.type="serviceruntime.googleapis.com/api/request_count" AND resource.labels.service="youtube.googleapis.com"';

                $request = new ListTimeSeriesRequest();
                $request->setName($projectName);
                $request->setFilter($filter);
                $request->setInterval($interval);

                // Execute query
                $timeSeries = $client->listTimeSeries($request);

                // Calculate total requests from time series data
                $totalRequests = 0;
                foreach ($timeSeries as $series) {
                    foreach ($series->getPoints() as $point) {
                        $value = $point->getValue();
                        if ($value->hasInt64Value()) {
                            $totalRequests += $value->getInt64Value();
                        } elseif ($value->hasDoubleValue()) {
                            $totalRequests += (int) $value->getDoubleValue();
                        }
                    }
                }

                // Clean up temp file
                @unlink($tempFile);

                // YouTube Data API v3 default daily quota is 10,000 units
                $dailyLimit = 10000;
                $remaining = max(0, $dailyLimit - $totalRequests);
                $percentage = $dailyLimit > 0 ? round(($totalRequests / $dailyLimit) * 100, 2) : 0;

                return [
                    'used' => $totalRequests,
                    'limit' => $dailyLimit,
                    'remaining' => $remaining,
                    'percentage' => $percentage,
                    'timestamp' => time(),
                ];

            } catch (\Exception $e) {
                Log::error('Google Cloud Monitoring API error', [
                    'error' => $e->getMessage(),
                    'project_id' => $this->projectId,
                ]);

                // Clean up temp file if it exists
                if (isset($tempFile) && file_exists($tempFile)) {
                    @unlink($tempFile);
                }

                throw new Exception("Failed to fetch quota data: " . $e->getMessage());
            }
        });
    }

    /**
     * Clear cached quota data
     */
    public function clearCache(): void
    {
        if (!empty($this->projectId)) {
            Cache::forget("youtube_quota_usage_{$this->projectId}");
        }
    }
}

