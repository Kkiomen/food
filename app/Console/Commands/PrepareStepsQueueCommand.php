<?php

namespace App\Console\Commands;

use App\Jobs\PrepareStepsJob;
use App\Models\ScrapRecipe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class PrepareStepsQueueCommand extends Command
{
    protected $signature = 'steps:queue
                            {--limit=0 : Limit number of jobs to dispatch (0 = no limit)}';

    protected $description = 'Queue prepared steps generation for recipes that don\'t have them yet';

    public function handle(): int
    {
        $query = ScrapRecipe::whereNull('prepared_steps')
            ->whereNotNull('steps');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No recipes without prepared steps found.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} recipes without prepared steps.");

        // Check how many jobs are already on the queue
        $pendingCount = Queue::size('chatgpt');
        if ($pendingCount > 0) {
            $this->warn("There are already {$pendingCount} jobs on the 'chatgpt' queue.");
            if (!$this->confirm('Do you want to continue dispatching?')) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $query->limit($limit);
            $this->info("Limiting to {$limit} jobs.");
        }

        $recipes = $query->get();
        $dispatched = 0;
        $skipped = 0;

        $this->withProgressBar($recipes, function (ScrapRecipe $recipe) use (&$dispatched, &$skipped) {
            // Double-check: skip if already has prepared_steps (race condition guard)
            if ($recipe->prepared_steps !== null) {
                $skipped++;
                return;
            }

            PrepareStepsJob::dispatch($recipe);
            $dispatched++;
        });

        $this->newLine(2);
        $this->info("Dispatched: {$dispatched} | Skipped: {$skipped}");

        if ($dispatched > 0) {
            $this->newLine();
            $this->line('Run 3 queue workers with:');
            $this->line('  <fg=cyan>php artisan queue:work --queue=chatgpt &</>');
            $this->line('  <fg=cyan>php artisan queue:work --queue=chatgpt &</>');
            $this->line('  <fg=cyan>php artisan queue:work --queue=chatgpt &</>');
        }

        return self::SUCCESS;
    }
}
