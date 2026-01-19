<?php

namespace App\Console\Commands;

use App\Services\ScraperFactory;
use Illuminate\Console\Command;

class ScrapDebugCommand extends Command
{
    protected $signature = 'scrap:debug {url}';

    protected $description = 'Debug scraper output for a recipe URL';

    public function handle(ScraperFactory $scraperFactory): int
    {
        $url = $this->argument('url');

        try {
            $type = $scraperFactory->detectTypeFromUrl($url);
            $scraper = $scraperFactory->getScraperByType($type);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Detected scraper: {$type}");
        $this->info("Scraping: {$url}");
        $this->newLine();

        $data = $scraper->scrapeRecipe($url);

        if (!$data) {
            $this->error('Failed to scrape the recipe.');
            return self::FAILURE;
        }

        $this->line("<fg=cyan>Name:</> {$data['name']}");
        $this->line("<fg=cyan>URL:</> {$data['url']}");
        $this->line("<fg=cyan>Author:</> " . ($data['author'] ?? '-'));
        $this->line("<fg=cyan>Published:</> " . ($data['published_at'] ?? '-'));
        $this->line("<fg=cyan>Modified:</> " . ($data['modified_at'] ?? '-'));
        $this->line("<fg=cyan>Category:</> " . ($data['category'] ?? '-'));
        $this->line("<fg=cyan>Cuisine:</> " . ($data['cuisine'] ?? '-'));
        $this->line("<fg=cyan>Diet:</> " . ($data['diet'] ?? '-'));
        if (isset($data['difficulty'])) {
            $this->line("<fg=cyan>Difficulty:</> " . ($data['difficulty'] ?? '-'));
        }
        $this->newLine();

        if ($data['description']) {
            $this->info('Description:');
            $this->line("  " . wordwrap($data['description'], 80, "\n  "));
            $this->newLine();
        }

        if ($data['keywords']) {
            $this->info('Keywords:');
            $this->line("  " . implode(', ', $data['keywords']));
            $this->newLine();
        }

        $this->info('Rating & Stats:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Rating', ($data['rating_value'] ?? '-') . ' / 5'],
                ['Votes', $data['rating_count'] ?? '-'],
                ['Comments', $data['comment_count'] ?? '-'],
            ]
        );

        $this->info('Time:');
        $this->table(
            ['Type', 'Value'],
            [
                ['Prep Time', $data['prep_time'] ?? '-'],
                ['Cook Time', $data['cook_time'] ?? '-'],
                ['Total Time', $data['total_time'] ?? '-'],
                ['Servings', $data['servings'] ?? '-'],
            ]
        );

        if ($data['nutrition']) {
            $this->info('Nutrition:');
            $nutritionRows = [];
            foreach ($data['nutrition'] as $key => $value) {
                $nutritionRows[] = [ucfirst($key), $value];
            }
            $this->table(['Nutrient', 'Value'], $nutritionRows);
        }

        if ($data['ingredients']) {
            $this->info('Ingredients:');
            foreach ($data['ingredients'] as $section) {
                if ($section['section']) {
                    $this->line("<fg=yellow>  [{$section['section']}]</>");
                }
                foreach ($section['items'] as $item) {
                    $qty = $item['qty'] ? "<fg=gray>{$item['qty']}</>" : '';
                    $this->line("    - {$item['name']} {$qty}");
                }
            }
            $this->newLine();
        }

        if ($data['steps']) {
            $this->info('Steps:');
            foreach ($data['steps'] as $step) {
                $name = $step['name'] ? " <fg=yellow>{$step['name']}</>" : '';
                $this->line("<fg=cyan>  {$step['step']}.</>{$name}");
                $this->line("     " . wordwrap($step['text'], 80, "\n     "));
                if (!empty($step['image'])) {
                    $this->line("     <fg=gray>Image: {$step['image']}</>");
                }
                $this->newLine();
            }
        }

        if ($data['images']) {
            $this->info('Images:');
            foreach ($data['images'] as $image) {
                $this->line("  - {$image}");
            }
        }

        return self::SUCCESS;
    }
}
