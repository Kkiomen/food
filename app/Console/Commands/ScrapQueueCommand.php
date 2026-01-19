<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeRecipeJob;
use App\Models\ScrapCategory;
use Illuminate\Console\Command;

class ScrapQueueCommand extends Command
{
    protected $signature = 'scrap:queue
                            {--limit=0 : Limit number of jobs to dispatch (0 = no limit)}
                            {--type=ania-gotuje : Type of scraper to use}';

    protected $description = 'Queue all unscraped categories for scraping';

    public function handle(): int
    {
        $type = $this->option('type');
        $limit = (int) $this->option('limit');

        $query = ScrapCategory::unscraped($type);

        $total = $query->count();

        if ($total === 0) {
            $this->info('No unscraped categories found.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} unscraped categories.");

        if ($limit > 0) {
            $query->limit($limit);
            $this->info("Limiting to {$limit} jobs.");
        }

        $categories = $query->get();
        $dispatched = 0;

        $this->withProgressBar($categories, function (ScrapCategory $category) use (&$dispatched) {
            ScrapeRecipeJob::dispatch($category);
            $dispatched++;
        });

        $this->newLine(2);
        $this->info("Dispatched {$dispatched} jobs to the 'scrap_categories' queue.");
        $this->newLine();
        $this->line('Run the queue worker with:');
        $this->line('  <fg=cyan>php artisan queue:work --queue=scrap_categories</>');

        return self::SUCCESS;
    }
}
