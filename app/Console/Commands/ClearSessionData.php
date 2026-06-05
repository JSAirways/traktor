<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class ClearSessionData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'session:clear-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all session-related data (sessions, tokens, cache) to fix invalid session/CSRF token issues';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing session-related data...');
        $this->newLine();

        $sessionDriver = config('session.driver');
        $cacheDriver = config('cache.default');

        // Clear database sessions if using database driver
        if ($sessionDriver === 'database') {
            $this->clearDatabaseSessions();
        } else {
            $this->clearFileSessions();
        }

        // Clear password reset tokens
        $this->clearPasswordResetTokens();

        // Clear cache
        if ($cacheDriver === 'database') {
            $this->clearDatabaseCache();
        } else {
            $this->clearApplicationCache();
        }

        // Clear personal access tokens (expired)
        $this->clearPersonalAccessTokens();

        $this->newLine();
        $this->info('✓ All session-related data cleared successfully!');
        $this->info('Users will need to log in again, and all CSRF tokens will be regenerated.');
    }

    /**
     * Clear database sessions table
     */
    protected function clearDatabaseSessions()
    {
        $table = config('session.table', 'sessions');
        
        if (Schema::hasTable($table)) {
            try {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->info("✓ Cleared {$count} sessions from '{$table}' table");
            } catch (\Exception $e) {
                $this->error("✗ Failed to clear sessions table: " . $e->getMessage());
            }
        } else {
            $this->warn("⚠ Sessions table '{$table}' does not exist (using file sessions?)");
        }
    }

    /**
     * Clear file-based sessions
     */
    protected function clearFileSessions()
    {
        $sessionPath = storage_path('framework/sessions');
        
        if (File::exists($sessionPath)) {
            try {
                $files = File::files($sessionPath);
                $count = count($files);
                
                // Delete all session files except .gitignore
                foreach ($files as $file) {
                    if ($file->getFilename() !== '.gitignore') {
                        File::delete($file->getPathname());
                    }
                }
                
                $this->info("✓ Cleared {$count} session files from storage/framework/sessions");
            } catch (\Exception $e) {
                $this->error("✗ Failed to clear session files: " . $e->getMessage());
            }
        } else {
            $this->warn("⚠ Session directory does not exist: {$sessionPath}");
        }
    }

    /**
     * Clear password reset tokens
     */
    protected function clearPasswordResetTokens()
    {
        if (Schema::hasTable('password_reset_tokens')) {
            try {
                $count = DB::table('password_reset_tokens')->count();
                DB::table('password_reset_tokens')->truncate();
                $this->info("✓ Cleared {$count} password reset tokens");
            } catch (\Exception $e) {
                $this->error("✗ Failed to clear password reset tokens: " . $e->getMessage());
            }
        } else {
            $this->warn("⚠ Password reset tokens table does not exist");
        }
    }

    /**
     * Clear database cache
     */
    protected function clearDatabaseCache()
    {
        $table = config('cache.stores.database.table', 'cache');
        
        if (Schema::hasTable($table)) {
            try {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->info("✓ Cleared {$count} cache entries from '{$table}' table");
            } catch (\Exception $e) {
                $this->error("✗ Failed to clear cache table: " . $e->getMessage());
            }
        } else {
            $this->warn("⚠ Cache table '{$table}' does not exist");
        }
    }

    /**
     * Clear application cache (for non-database drivers)
     */
    protected function clearApplicationCache()
    {
        try {
            Cache::flush();
            $this->info("✓ Cleared application cache");
        } catch (\Exception $e) {
            $this->error("✗ Failed to clear cache: " . $e->getMessage());
        }
    }

    /**
     * Clear expired personal access tokens
     */
    protected function clearPersonalAccessTokens()
    {
        if (Schema::hasTable('personal_access_tokens')) {
            try {
                $count = DB::table('personal_access_tokens')
                    ->where(function($query) {
                        $query->where('expires_at', '<', now())
                              ->orWhere(function($q) {
                                  $q->whereNull('expires_at')
                                    ->where('created_at', '<', now()->subDays(30));
                              });
                    })
                    ->count();
                
                DB::table('personal_access_tokens')
                    ->where(function($query) {
                        $query->where('expires_at', '<', now())
                              ->orWhere(function($q) {
                                  $q->whereNull('expires_at')
                                    ->where('created_at', '<', now()->subDays(30));
                              });
                    })
                    ->delete();
                
                if ($count > 0) {
                    $this->info("✓ Cleared {$count} expired personal access tokens");
                } else {
                    $this->info("✓ No expired personal access tokens to clear");
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to clear personal access tokens: " . $e->getMessage());
            }
        } else {
            $this->warn("⚠ Personal access tokens table does not exist");
        }
    }
}
