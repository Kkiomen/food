<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeRecipeJob;
use App\Models\ScrapCategory;
use App\Models\ScrapRecipe;
use App\Services\ScraperFactory;
use Illuminate\Console\Command;

class ScrapTestCommand extends Command
{
    protected $signature = 'scrap:test {id : ID of ScrapCategory to test}';

    protected $description = 'Test scraping a recipe by ScrapCategory ID (runs synchronously)';

    public function handle(ScraperFactory $scraperFactory): int
    {
        $id = $this->argument('id');

        $category = ScrapCategory::find($id);

        if (!$category) {
            $this->error("ScrapCategory with ID {$id} not found.");
            return self::FAILURE;
        }

        $this->info("Found ScrapCategory:");
        $this->line("  ID: {$category->id}");
        $this->line("  URL: {$category->url}");
        $this->line("  Type: {$category->type}");
        $this->line("  Is Scraped: " . ($category->is_scraped ? 'Yes' : 'No'));
        $this->newLine();

        $this->info("Executing scrape job synchronously...");
        $this->newLine();

        try {
            $job = new ScrapeRecipeJob($category);
            $job->handle($scraperFactory);

            $this->info("✓ Scraping completed successfully!");
            $this->newLine();

            // Check if recipe was created
            $recipe = ScrapRecipe::where('url', $category->url)->first();
            if ($recipe) {
                $this->info("Recipe saved:");
                $this->line("  Name: {$recipe->name}");
                $this->line("  ID: {$recipe->id}");
            }

            // Check if category was marked as scraped
            $category->refresh();
            if ($category->is_scraped) {
                $this->info("Category marked as scraped.");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Error during scraping:");
            $this->error($e->getMessage());
            $this->newLine();
            $this->line("Stack trace:");
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
