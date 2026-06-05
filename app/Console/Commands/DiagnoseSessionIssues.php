<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class DiagnoseSessionIssues extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'session:diagnose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose session and CSRF token issues after database migration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Diagnosing Session and CSRF Token Issues...');
        $this->newLine();

        $issues = [];
        $warnings = [];
        $info = [];

        // Check APP_KEY
        $this->checkAppKey($issues, $warnings, $info);

        // Check session driver configuration
        $this->checkSessionDriver($issues, $warnings, $info);

        // Check session storage
        $this->checkSessionStorage($issues, $warnings, $info);

        // Check for old session data
        $this->checkOldSessionData($issues, $warnings, $info);

        // Check cache configuration
        $this->checkCacheConfiguration($info);

        // Display results
        $this->displayResults($issues, $warnings, $info);

        // Provide recommendations
        $this->provideRecommendations($issues, $warnings);
    }

    /**
     * Check APP_KEY configuration
     */
    protected function checkAppKey(&$issues, &$warnings, &$info)
    {
        $this->line('📋 Checking APP_KEY...');
        
        $appKey = config('app.key');
        
        if (empty($appKey)) {
            $issues[] = 'APP_KEY is not set in .env file';
        } else {
            $info[] = '✓ APP_KEY is configured';
            
            // Check if it's the default Laravel key
            if ($appKey === 'base64:') {
                $warnings[] = 'APP_KEY appears to be empty or default - generate a new one with: php artisan key:generate';
            }
        }
    }

    /**
     * Check session driver configuration
     */
    protected function checkSessionDriver(&$issues, &$warnings, &$info)
    {
        $this->line('📋 Checking Session Driver...');
        
        $driver = config('session.driver');
        $info[] = "Session driver: {$driver}";
        
        if ($driver === 'database') {
            $table = config('session.table', 'sessions');
            if (!Schema::hasTable($table)) {
                $issues[] = "Sessions table '{$table}' does not exist but driver is set to 'database'";
                $warnings[] = "Run: php artisan session:table && php artisan migrate";
            } else {
                $info[] = "✓ Sessions table '{$table}' exists";
            }
        } elseif ($driver === 'file') {
            $sessionPath = storage_path('framework/sessions');
            if (!File::exists($sessionPath)) {
                $issues[] = "Session directory does not exist: {$sessionPath}";
            } else {
                $info[] = "✓ Session directory exists: {$sessionPath}";
            }
        }
    }

    /**
     * Check session storage for old data
     */
    protected function checkSessionStorage(&$issues, &$warnings, &$info)
    {
        $this->line('📋 Checking Session Storage...');
        
        $driver = config('session.driver');
        
        if ($driver === 'database') {
            $table = config('session.table', 'sessions');
            if (Schema::hasTable($table)) {
                try {
                    $count = DB::table($table)->count();
                    $oldCount = DB::table($table)
                        ->where('last_activity', '<', now()->subDays(1)->timestamp)
                        ->count();
                    
                    $info[] = "Sessions in database: {$count}";
                    
                    if ($count > 0) {
                        if ($oldCount > 0) {
                            $warnings[] = "Found {$oldCount} old sessions (older than 1 day) - these may cause CSRF issues";
                        }
                        
                        // Check for potentially corrupted sessions
                        $recentCount = DB::table($table)
                            ->where('last_activity', '>=', now()->subHours(1)->timestamp)
                            ->count();
                        
                        if ($recentCount > 0 && $count > 10) {
                            $warnings[] = "Found {$count} total sessions - many sessions may indicate imported data from live server";
                        }
                    }
                } catch (\Exception $e) {
                    $warnings[] = "Could not check sessions table: " . $e->getMessage();
                }
            }
        } elseif ($driver === 'file') {
            $sessionPath = storage_path('framework/sessions');
            if (File::exists($sessionPath)) {
                $files = File::files($sessionPath);
                $count = count(array_filter($files, function($file) {
                    return $file->getFilename() !== '.gitignore';
                }));
                
                $info[] = "Session files: {$count}";
                
                if ($count > 10) {
                    $warnings[] = "Found {$count} session files - many files may indicate imported data or old sessions";
                }
            }
        }
    }

    /**
     * Check for old session data that might cause issues
     */
    protected function checkOldSessionData(&$issues, &$warnings, &$info)
    {
        $this->line('📋 Checking for Old Session Data...');
        
        $driver = config('session.driver');
        
        if ($driver === 'database') {
            $table = config('session.table', 'sessions');
            if (Schema::hasTable($table)) {
                try {
                    // Check for sessions that might be from before migration
                    $veryOldCount = DB::table($table)
                        ->where('last_activity', '<', now()->subDays(7)->timestamp)
                        ->count();
                    
                    if ($veryOldCount > 0) {
                        $warnings[] = "Found {$veryOldCount} sessions older than 7 days - likely from imported database";
                    }
                } catch (\Exception $e) {
                    // Ignore errors
                }
            }
        }
    }

    /**
     * Check cache configuration
     */
    protected function checkCacheConfiguration(&$info)
    {
        $this->line('📋 Checking Cache Configuration...');
        
        $cacheDriver = config('cache.default');
        $info[] = "Cache driver: {$cacheDriver}";
        
        if ($cacheDriver === 'database') {
            $table = config('cache.stores.database.table', 'cache');
            if (Schema::hasTable($table)) {
                try {
                    $count = DB::table($table)->count();
                    $info[] = "Cache entries: {$count}";
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }
    }

    /**
     * Display diagnostic results
     */
    protected function displayResults($issues, $warnings, $info)
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('📊 Diagnostic Results');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        if (!empty($info)) {
            $this->line('ℹ️  Information:');
            foreach ($info as $item) {
                $this->line("   {$item}");
            }
            $this->newLine();
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($warnings as $warning) {
                $this->warn("   {$warning}");
            }
            $this->newLine();
        }

        if (!empty($issues)) {
            $this->error('❌ Issues Found:');
            foreach ($issues as $issue) {
                $this->error("   {$issue}");
            }
            $this->newLine();
        } else {
            $this->info('✓ No critical issues found!');
            $this->newLine();
        }
    }

    /**
     * Provide recommendations
     */
    protected function provideRecommendations($issues, $warnings)
    {
        if (empty($issues) && empty($warnings)) {
            $this->info('✅ Everything looks good!');
            return;
        }

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('💡 Recommendations');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        $hasOldSessions = false;
        $hasAppKeyIssue = false;

        foreach ($issues as $issue) {
            if (str_contains($issue, 'APP_KEY')) {
                $hasAppKeyIssue = true;
            }
        }

        foreach ($warnings as $warning) {
            if (str_contains($warning, 'old sessions') || str_contains($warning, 'older than')) {
                $hasOldSessions = true;
            }
        }

        if ($hasOldSessions || !empty($warnings)) {
            $this->line('1. Clear all session data:');
            $this->line('   php artisan session:clear-all');
            $this->newLine();
        }

        if ($hasAppKeyIssue) {
            $this->line('2. Generate a new APP_KEY:');
            $this->line('   php artisan key:generate');
            $this->newLine();
        }

        $this->line('3. Clear browser cookies for localhost');
        $this->line('   - Open browser developer tools (F12)');
        $this->line('   - Go to Application/Storage tab');
        $this->line('   - Clear cookies for localhost');
        $this->newLine();

        $this->line('4. If issues persist, check:');
        $this->line('   - APP_KEY in .env matches your local environment');
        $this->line('   - Session driver matches between environments');
        $this->line('   - No old session files in storage/framework/sessions');
        $this->newLine();
    }
}
