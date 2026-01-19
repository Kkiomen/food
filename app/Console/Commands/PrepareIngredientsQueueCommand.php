<?php

namespace App\Console\Commands;

use App\Jobs\PrepareIngredientsJob;
use App\Models\ScrapRecipe;
use Illuminate\Console\Command;

class PrepareIngredientsQueueCommand extends Command
{
    protected $signature = 'ingredients:queue
                            {--limit=0 : Limit number of jobs to dispatch (0 = no limit)}';

    protected $description = 'Queue all recipes without prepared ingredients for processing';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $query = ScrapRecipe::whereNull('prepared_ingredients')
            ->whereNotNull('ingredients');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No recipes without prepared ingredients found.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} recipes without prepared ingredients.");

        if ($limit > 0) {
            $query->limit($limit);
            $this->info("Limiting to {$limit} jobs.");
        }

        $recipes = $query->get();
        $dispatched = 0;

        $this->withProgressBar($recipes, function (ScrapRecipe $recipe) use (&$dispatched) {
            PrepareIngredientsJob::dispatch($recipe);
            $dispatched++;
        });

        $this->newLine(2);
        $this->info("Dispatched {$dispatched} jobs to the 'prepare_ingredients' queue.");
        $this->newLine();
        $this->line('Run the queue worker with:');
        $this->line('  <fg=cyan>php artisan queue:work --queue=prepare_ingredients</>');

        return self::SUCCESS;
    }
}
