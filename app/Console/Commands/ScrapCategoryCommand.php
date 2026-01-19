<?php

namespace App\Console\Commands;

use App\Models\ScrapCategory;
use App\Services\ScraperFactory;
use Illuminate\Console\Command;

class ScrapCategoryCommand extends Command
{
    protected $signature = 'scrap:category {url}
                            {--limit=0 : Limit number of pages to scrape (0 = no limit)}';

    protected $description = 'Scrape recipe URLs from a category page';

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

        $this->info("Detected scraper type: {$type}");
        $this->info("Fetching category page: {$url}");

        $html = $scraper->fetchPage($url);
        if (!$html) {
            $this->error('Failed to fetch the category page.');
            return self::FAILURE;
        }

        $lastPage = $scraper->getLastPageNumber($html);
        $limit = (int) $this->option('limit');

        $pagesToProcess = $lastPage;
        if ($limit > 0 && $limit < $lastPage) {
            $pagesToProcess = $limit;
            $this->info("Detected {$lastPage} page(s) in this category, but limiting to {$limit} page(s).");
        } else {
            $this->info("Detected {$lastPage} page(s) in this category.");
        }

        $totalFound = 0;
        $newUrls = 0;
        $duplicates = 0;

        for ($page = 1; $page <= $pagesToProcess; $page++) {
            $pageUrl = $this->buildPageUrl($url, $page, $type);

            $this->line("Fetching page {$page}/{$lastPage}: {$pageUrl}");

            if ($page > 1) {
                $html = $scraper->fetchPage($pageUrl);
                if (!$html) {
                    $this->warn("Failed to fetch page {$page}, skipping...");
                    continue;
                }
            }

            $recipeUrls = $scraper->parseCategoryPage($html);
            $totalFound += count($recipeUrls);

            foreach ($recipeUrls as $recipeUrl) {
                $result = ScrapCategory::firstOrCreate(
                    ['url' => $recipeUrl],
                    ['type' => $type, 'is_scraped' => false]
                );

                if ($result->wasRecentlyCreated) {
                    $newUrls++;
                } else {
                    $duplicates++;
                }
            }

            if ($page < $pagesToProcess) {
                $delay = rand(1, 3);
                $this->line("Waiting {$delay} seconds...");
                sleep($delay);
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total URLs found', $totalFound],
                ['New URLs added', $newUrls],
                ['Duplicates skipped', $duplicates],
            ]
        );

        $this->info('Category scraping completed!');

        return self::SUCCESS;
    }

    private function buildPageUrl(string $baseUrl, int $page, string $type): string
    {
        if ($page === 1) {
            return $baseUrl;
        }

        return match ($type) {
            ScraperFactory::TYPE_ANIA_GOTUJE => rtrim($baseUrl, '/') . "/strona/{$page}",
            ScraperFactory::TYPE_ZE_SMAKIEM_NA_TY => rtrim($baseUrl, '/') . "/page/{$page}/",
            ScraperFactory::TYPE_POPROSTUPYCHA => rtrim($baseUrl, '/') . "/page/{$page}/",
            ScraperFactory::TYPE_SMAKER => $this->buildSmakerPageUrl($baseUrl, $page),
            default => rtrim($baseUrl, '/') . "/page/{$page}",
        };
    }

    private function buildSmakerPageUrl(string $baseUrl, int $page): string
    {
        $parsedUrl = parse_url($baseUrl);
        $query = [];

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        $query['page'] = $page;

        $url = $parsedUrl['scheme'] ?? 'https';
        $url .= '://';
        $url .= $parsedUrl['host'] ?? '';
        $url .= $parsedUrl['path'] ?? '';
        $url .= '?' . http_build_query($query);

        return $url;
    }
}
