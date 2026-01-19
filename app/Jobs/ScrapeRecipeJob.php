<?php

namespace App\Jobs;

use App\Models\ScrapCategory;
use App\Models\ScrapRecipe;
use App\Services\ScraperFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScrapeRecipeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ScrapCategory $category
    ) {
        $this->onQueue('scrap_categories');
    }

    public function handle(ScraperFactory $scraperFactory): void
    {
        $url = $this->category->url;
        $type = $this->category->type;

        // Random delay to avoid being blocked
        sleep(rand(1, 3));

        Log::info("Scraping recipe: {$url} (type: {$type})");

        $scraper = $scraperFactory->getScraperByType($type);
        $data = $scraper->scrapeRecipe($url);

        if (!$data || empty($data['name'])) {
            Log::warning("Failed to scrape recipe: {$url}");
            return;
        }

        $scrapRecipe = ScrapRecipe::updateOrCreate(
            ['url' => $url],
            [
                'name' => $data['name'],
                'author' => $data['author'],
                'published_at' => $data['published_at'],
                'modified_at' => $data['modified_at'],
                'category' => $data['category'],
                'cuisine' => $data['cuisine'],
                'description' => $data['description'],
                'prep_time' => $data['prep_time'],
                'cook_time' => $data['cook_time'],
                'total_time' => $data['total_time'],
                'servings' => $data['servings'],
                'nutrition' => $data['nutrition'],
                'ingredients' => $data['ingredients'],
                'steps' => $data['steps'],
                'images' => $data['images'],
                'rating_value' => $data['rating_value'],
                'rating_count' => $data['rating_count'],
                'comment_count' => $data['comment_count'],
                'diet' => $data['diet'],
                'keywords' => $data['keywords'],
            ]
        );

        $this->category->markAsScraped();

        PrepareIngredientsJob::dispatch($scrapRecipe);

        Log::info("Successfully scraped: {$data['name']}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to scrape recipe: {$this->category->url}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
