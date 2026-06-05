<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Video;
use App\Models\Playlist;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

class MigrateElliotToJonan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:migrate-elliot-to-jonan 
                            {--dry-run : Run without making changes to preview what will happen}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate video content from Elliot to a new child profile under Jonan';

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
        $elliot = User::where('username', 'Elliot')->first();
        $jonan = User::where('username', 'Jonan')->first();

        if (!$elliot) {
            $this->error('❌ User "Elliot" not found');
            return 1;
        }

        if (!$jonan) {
            $this->error('❌ User "Jonan" not found');
            return 1;
        }

        // Check if Jonan is a parent account (not a child)
        if ($jonan->parent_id) {
            $this->error('❌ User "Jonan" is a child account. Cannot create child profiles under a child account.');
            return 1;
        }

        $this->info("✓ Found user: Elliot (ID: {$elliot->id}, Role: {$elliot->role})");
        $this->info("✓ Found user: Jonan (ID: {$jonan->id}, Role: {$jonan->role})");
        $this->newLine();

        // Count videos and playlists
        $elliotVideos = Video::where('user_id', $elliot->id)->count();
        $elliotPlaylists = Playlist::where('user_id', $elliot->id)->count();

        $this->info("📊 Current Data for Elliot:");
        $this->line("   Videos: {$elliotVideos}");
        $this->line("   Playlists: {$elliotPlaylists}");
        $this->newLine();

        if ($elliotVideos === 0 && $elliotPlaylists === 0) {
            $this->warn('⚠ No content found for Elliot. Nothing to migrate.');
            return 0;
        }

        if (!$dryRun) {
            if (!$this->confirm('Do you want to proceed with the migration?', true)) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        DB::beginTransaction();
        
        try {
            // Step 1: Create child "Elliot" under Jonan
            $this->info('📝 Step 1: Creating child profile "Elliot" under Jonan...');
            $elliotChild = $this->createChildProfile($jonan, 'Elliot', null, $dryRun);
            
            if ($elliotChild) {
                $this->info("   ✓ Created child profile: Elliot (ID: {$elliotChild->id}, Username: {$elliotChild->username})");
                
                // Step 2: Move Elliot's videos to child Elliot
                if ($elliotVideos > 0) {
                    $this->info('📝 Step 2: Moving videos from Elliot to child Elliot...');
                    $movedVideos = $this->moveVideos($elliot->id, $elliotChild->id, $dryRun);
                    $this->info("   ✓ Moved {$movedVideos} videos");
                }
                
                // Step 3: Move Elliot's playlists to child Elliot
                if ($elliotPlaylists > 0) {
                    $this->info('📝 Step 3: Moving playlists from Elliot to child Elliot...');
                    $movedPlaylists = $this->movePlaylists($elliot->id, $elliotChild->id, $dryRun);
                    $this->info("   ✓ Moved {$movedPlaylists} playlists");
                }

                // Step 4: Invalidate cache for the new child profile
                if (!$dryRun) {
                    $this->info('📝 Step 4: Invalidating cache...');
                    Cache::forget("user_gallery_{$elliotChild->username}");
                    $this->info("   ✓ Cache invalidated for user_gallery_{$elliotChild->username}");
                } else {
                    $this->line("   [DRY RUN] Would invalidate cache for user_gallery_{$elliotChild->username}");
                }
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
                $this->info('   - Child "Elliot" created under Jonan');
                $this->info('   - All videos and playlists migrated');
                $this->info('   - Cache invalidated');
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
            $this->warn("   ⚠ Will use existing child profile for migration");
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
        }

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

