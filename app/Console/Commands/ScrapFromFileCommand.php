<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeRecipeJob;
use App\Models\ScrapCategory;
use App\Services\ScraperFactory;
use Illuminate\Console\Command;

class ScrapFromFileCommand extends Command
{
    protected $signature = 'scrap:file
                            {file : Path to .txt file with URLs (one per line)}';

    protected $description = 'Queue scraping jobs from a file with category URLs';

    public function __construct(
        private ScraperFactory $scraperFactory,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $urls = array_filter(array_map('trim', $lines), fn ($line) => $line !== '' && !str_starts_with($line, '#'));

        if (empty($urls)) {
            $this->error('No URLs found in file.');
            return self::FAILURE;
        }

        $this->info('Found '.count($urls).' URLs in file.');

        $dispatched = 0;
        $skipped = 0;

        $this->withProgressBar($urls, function (string $url) use (&$dispatched, &$skipped) {
            try {
                $type = $this->scraperFactory->detectTypeFromUrl($url);
            } catch (\InvalidArgumentException $e) {
                $this->newLine();
                $this->warn("Skipping unknown URL: {$url}");
                $skipped++;
                return;
            }

            $category = ScrapCategory::firstOrCreate(
                ['url' => $url],
                ['type' => $type, 'is_scraped' => false],
            );

            if ($category->is_scraped) {
                $skipped++;
                return;
            }

            ScrapeRecipeJob::dispatch($category);
            $dispatched++;
        });

        $this->newLine(2);
        $this->info("Dispatched: {$dispatched} | Skipped (already scraped/invalid): {$skipped}");

        if ($dispatched > 0) {
            $this->newLine();
            $this->line('Run the queue worker with:');
            $this->line('  <fg=cyan>php artisan queue:work --queue=scrap_categories</>');
        }

        return self::SUCCESS;
    }
}
