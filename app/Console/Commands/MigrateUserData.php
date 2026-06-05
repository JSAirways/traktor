<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Video;
use App\Models\Playlist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class MigrateUserData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:migrate-data 
                            {--dry-run : Run without making changes to preview what will happen}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from Vera account to Andrea account and create child profiles';

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

        // Find the users
        $veraParent = User::where('username', 'Vera')->whereNull('parent_id')->first();
        $andreaParent = User::where('username', 'Andrea')->whereNull('parent_id')->first();

        if (!$veraParent) {
            $this->error('❌ User "Vera" (parent account) not found');
            return 1;
        }

        if (!$andreaParent) {
            $this->error('❌ User "Andrea" (parent account) not found');
            return 1;
        }

        $this->info("✓ Found parent account: Vera (ID: {$veraParent->id})");
        $this->info("✓ Found parent account: Andrea (ID: {$andreaParent->id})");
        $this->newLine();

        // Count videos and playlists
        $veraVideos = Video::where('user_id', $veraParent->id)->count();
        $andreaVideos = Video::where('user_id', $andreaParent->id)->count();
        $veraPlaylists = Playlist::where('user_id', $veraParent->id)->count();
        $andreaPlaylists = Playlist::where('user_id', $andreaParent->id)->count();

        $this->info("📊 Current Data:");
        $this->line("   Vera videos: {$veraVideos}");
        $this->line("   Vera playlists: {$veraPlaylists}");
        $this->line("   Andrea videos: {$andreaVideos}");
        $this->line("   Andrea playlists: {$andreaPlaylists}");
        $this->newLine();

        if (!$dryRun) {
            if (!$this->confirm('Do you want to proceed with the migration?', true)) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        DB::beginTransaction();
        
        try {
            // Step 1: Create child "Vera" under Andrea (no PIN)
            $this->info('📝 Step 1: Creating child profile "Vera" under Andrea...');
            $veraChild = $this->createChildProfile($andreaParent, 'Vera', null, $dryRun);
            
            if ($veraChild) {
                $this->info("   ✓ Created child profile: Vera (ID: {$veraChild->id}, Username: {$veraChild->username})");
                
                // Step 2: Move Vera's videos to child Vera
                $this->info('📝 Step 2: Moving videos from parent Vera to child Vera...');
                $movedVideos = $this->moveVideos($veraParent->id, $veraChild->id, $dryRun);
                $this->info("   ✓ Moved {$movedVideos} videos");
                
                // Step 3: Move Vera's playlists to child Vera
                if ($veraPlaylists > 0) {
                    $this->info('📝 Step 3: Moving playlists from parent Vera to child Vera...');
                    $movedPlaylists = $this->movePlaylists($veraParent->id, $veraChild->id, $dryRun);
                    $this->info("   ✓ Moved {$movedPlaylists} playlists");
                }
            }

            // Step 4: Create child "Andrea" under Andrea (PIN: 1234)
            $this->info('📝 Step 4: Creating child profile "Andrea" under Andrea...');
            $andreaChild = $this->createChildProfile($andreaParent, 'Andrea', '1234', $dryRun);
            
            if ($andreaChild) {
                $this->info("   ✓ Created child profile: Andrea (ID: {$andreaChild->id}, Username: {$andreaChild->username})");
                
                // Step 5: Move Andrea's videos to child Andrea
                $this->info('📝 Step 5: Moving videos from parent Andrea to child Andrea...');
                $movedVideos = $this->moveVideos($andreaParent->id, $andreaChild->id, $dryRun);
                $this->info("   ✓ Moved {$movedVideos} videos");
                
                // Step 6: Move Andrea's playlists to child Andrea
                if ($andreaPlaylists > 0) {
                    $this->info('📝 Step 6: Moving playlists from parent Andrea to child Andrea...');
                    $movedPlaylists = $this->movePlaylists($andreaParent->id, $andreaChild->id, $dryRun);
                    $this->info("   ✓ Moved {$movedPlaylists} playlists");
                }
            }

            // Step 7: Delete parent account Vera
            if (!$dryRun) {
                $this->info('📝 Step 7: Deleting parent account Vera...');
                
                // Check if Vera has any remaining data
                $remainingVideos = Video::where('user_id', $veraParent->id)->count();
                $remainingPlaylists = Playlist::where('user_id', $veraParent->id)->count();
                $remainingChildren = $veraParent->children()->count();
                
                if ($remainingVideos > 0 || $remainingPlaylists > 0 || $remainingChildren > 0) {
                    $this->warn("   ⚠ Warning: Vera still has {$remainingVideos} videos, {$remainingPlaylists} playlists, and {$remainingChildren} children");
                }
                
                // Delete the user (cascade will handle related data)
                $veraParent->delete();
                $this->info("   ✓ Deleted parent account Vera (ID: {$veraParent->id})");
            } else {
                $this->line("   [DRY RUN] Would delete parent account Vera (ID: {$veraParent->id})");
            }

            if ($dryRun) {
                DB::rollBack();
                $this->newLine();
                $this->info('✅ Dry run completed. No changes were made.');
                $this->info('Run without --dry-run to apply changes.');
            } else {
                DB::commit();
                $this->newLine();
                $this->info('✅ Migration completed successfully!');
                $this->info('   - Child "Vera" created under Andrea (no PIN)');
                $this->info('   - Child "Andrea" created under Andrea (PIN: 1234)');
                $this->info('   - All videos and playlists migrated');
                $this->info('   - Parent account "Vera" deleted');
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
     * Create a child profile
     */
    protected function createChildProfile(User $parent, string $name, ?string $pin, bool $dryRun = false): ?User
    {
        // Check if child already exists
        $existingChild = $parent->children()->where('name', $name)->first();
        if ($existingChild) {
            $this->warn("   ⚠ Child '{$name}' already exists (ID: {$existingChild->id})");
            return $existingChild;
        }

        if ($dryRun) {
            $username = User::generateUniqueUsernameFromName($name);
            $this->line("   [DRY RUN] Would create child: {$name} (username: {$username})");
            return null;
        }

        // Generate unique username
        $username = User::generateUniqueUsernameFromName($name);
        $dummyEmail = $username . '@child.local';
        $dummyPassword = Hash::make(Str::random(32));

        $child = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $dummyEmail,
            'password' => $dummyPassword,
            'role' => 'user',
            'parent_id' => $parent->id,
            'is_viewable' => true,
            'account_status' => 'active',
            'locale' => $parent->locale ?? config('app.locale', 'en'),
        ]);

        // Set PIN if provided
        if ($pin !== null) {
            $child->setViewPin($pin);
            // Note: pin_enabled column may not exist, so we just set the PIN
            // The hasPin() method will work based on view_pin being set
        }
        // If no PIN, view_pin remains null and hasPin() will return false

        return $child;
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
