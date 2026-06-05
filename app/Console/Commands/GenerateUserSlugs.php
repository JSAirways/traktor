<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateUserSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:generate-slugs {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate slugs for all users that are missing slugs or have invalid slugs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all users
        $users = User::all();
        $total = $users->count();
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $this->info("Found {$total} users to check.");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($users as $user) {
            try {
                // Check if user needs a slug
                $needsSlug = empty($user->slug) || 
                             !preg_match('/^[a-z0-9_-]+$/', $user->slug) ||
                             $user->slug !== User::generateSlugFromUsername($user->username);

                if ($needsSlug) {
                    // Generate new slug from username
                    $newSlug = User::generateUniqueSlugFromUsername($user->username, $user->id);
                    
                    if (!$dryRun) {
                        $user->slug = $newSlug;
                        $user->save();
                    }
                    
                    $updated++;
                    
                    if ($this->getOutput()->isVerbose()) {
                        $this->newLine();
                        $this->line("User ID {$user->id} ({$user->username}):");
                        $this->line("  Old slug: " . ($user->getOriginal('slug') ?? 'NULL'));
                        $this->line("  New slug: {$newSlug}");
                    }
                } else {
                    $skipped++;
                }
            } catch (\Exception $e) {
                $errors++;
                if ($this->getOutput()->isVerbose()) {
                    $this->newLine();
                    $this->error("Error processing user ID {$user->id}: " . $e->getMessage());
                }
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Updated', $updated],
                ['Skipped (already valid)', $skipped],
                ['Errors', $errors],
                ['Total', $total],
            ]
        );

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->info("Successfully updated {$updated} user(s).");
        }

        return Command::SUCCESS;
    }
}
