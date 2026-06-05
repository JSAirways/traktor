<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Video;
use App\Models\Playlist;
use App\Models\DeviceChildVisibility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateAndreaChildToParent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:migrate-andrea-child 
                            {--dry-run : Run without making changes to preview what will happen}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate all content from child profile Andrea to parent profile Andrea and delete the child profile';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Find the parent profile "Andrea"
        $andreaParent = User::where('username', 'Andrea')
            ->whereNull('parent_id')
            ->first();

        if (!$andreaParent) {
            $this->error('❌ Parent profile "Andrea" not found');
            return 1;
        }

        // Find the child profile "Andrea" (may have different username like "andrea-1")
        $andreaChild = User::where('name', 'Andrea')
            ->whereNotNull('parent_id')
            ->where('parent_id', $andreaParent->id)
            ->first();

        if (!$andreaChild) {
            $this->error('❌ Child profile "Andrea" not found under parent "Andrea"');
            return 1;
        }

        $this->info("✓ Found parent profile: Andrea (ID: {$andreaParent->id})");
        $this->info("✓ Found child profile: Andrea (ID: {$andreaChild->id})");
        $this->newLine();

        // Count content
        $childVideos = Video::where('user_id', $andreaChild->id)->count();
        $childPlaylists = Playlist::where('user_id', $andreaChild->id)->count();
        $parentVideos = Video::where('user_id', $andreaParent->id)->count();
        $parentPlaylists = Playlist::where('user_id', $andreaParent->id)->count();
        $deviceVisibilityRecords = DeviceChildVisibility::where('child_user_id', $andreaChild->id)->count();

        $this->info("📊 Current Data:");
        $this->line("   Child Andrea videos: {$childVideos}");
        $this->line("   Child Andrea playlists: {$childPlaylists}");
        $this->line("   Parent Andrea videos: {$parentVideos}");
        $this->line("   Parent Andrea playlists: {$parentPlaylists}");
        $this->line("   Device visibility records for child: {$deviceVisibilityRecords}");
        $this->newLine();

        if ($childVideos === 0 && $childPlaylists === 0) {
            $this->warn('⚠️  Child profile has no content to migrate');
        }

        $force = $this->option('force');
        
        if (!$dryRun && !$force) {
            if (!$this->confirm('Do you want to proceed with the migration?', true)) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        DB::beginTransaction();
        
        try {
            // Step 1: Move videos from child to parent
            if ($childVideos > 0) {
                $this->info('📝 Step 1: Moving videos from child Andrea to parent Andrea...');
                $movedVideos = $this->moveVideos($andreaChild->id, $andreaParent->id, $dryRun);
                $this->info("   ✓ Moved {$movedVideos} videos");
            }

            // Step 2: Move playlists from child to parent
            if ($childPlaylists > 0) {
                $this->info('📝 Step 2: Moving playlists from child Andrea to parent Andrea...');
                $movedPlaylists = $this->movePlaylists($andreaChild->id, $andreaParent->id, $dryRun);
                $this->info("   ✓ Moved {$movedPlaylists} playlists");
            }

            // Step 3: Delete device visibility records (will be cascade deleted, but explicit for clarity)
            if ($deviceVisibilityRecords > 0) {
                $this->info('📝 Step 3: Removing device visibility records for child Andrea...');
                if (!$dryRun) {
                    DeviceChildVisibility::where('child_user_id', $andreaChild->id)->delete();
                }
                $this->info("   ✓ Removed {$deviceVisibilityRecords} device visibility records");
            }

            // Step 4: Delete child profile
            $childId = $andreaChild->id;
            $this->info('📝 Step 4: Deleting child profile Andrea...');
            if (!$dryRun) {
                $andreaChild->delete();
            }
            $this->info("   ✓ Deleted child profile: Andrea (ID: {$childId})");

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->info('✅ DRY RUN completed - No changes were made');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('✅ Migration completed successfully!');
                $totalVideos = $parentVideos + $childVideos;
                $totalPlaylists = $parentPlaylists + $childPlaylists;
                $this->info("   Parent Andrea now has: {$totalVideos} videos, {$totalPlaylists} playlists");
            }

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Migration failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Move videos from one user to another
     */
    protected function moveVideos(int $fromUserId, int $toUserId, bool $dryRun = false): int
    {
        $videos = Video::where('user_id', $fromUserId)->get();
        $count = $videos->count();

        if ($dryRun) {
            $this->line("   [DRY RUN] Would move {$count} videos from user {$fromUserId} to user {$toUserId}");
            return $count;
        }

        Video::where('user_id', $fromUserId)->update(['user_id' => $toUserId]);
        
        return $count;
    }

    /**
     * Move playlists from one user to another
     */
    protected function movePlaylists(int $fromUserId, int $toUserId, bool $dryRun = false): int
    {
        $playlists = Playlist::where('user_id', $fromUserId)->get();
        $count = $playlists->count();

        if ($dryRun) {
            $this->line("   [DRY RUN] Would move {$count} playlists from user {$fromUserId} to user {$toUserId}");
            return $count;
        }

        // Move playlists
        Playlist::where('user_id', $fromUserId)->update(['user_id' => $toUserId]);
        
        // Also update videos that belong to these playlists
        $playlistIds = $playlists->pluck('id')->toArray();
        if (!empty($playlistIds)) {
            Video::whereIn('playlist_id', $playlistIds)->update(['user_id' => $toUserId]);
        }
        
        return $count;
    }
}
