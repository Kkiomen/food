<?php

namespace App\Services;

use App\Services\Contracts\RecipeScraperInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AniaGotujeScraper implements RecipeScraperInterface
{
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const BASE_URL = 'https://aniagotuje.pl';

    public function fetchPage(string $url): ?string
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'pl-PL,pl;q=0.9,en-US;q=0.8,en;q=0.7',
                ])->timeout(30)->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning("Failed to fetch page: {$url}", ['status' => $response->status()]);
            return null;
        } catch (\Exception $e) {
            Log::error("Error fetching page: {$url}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function parseCategoryPage(string $html): array
    {
        $urls = [];
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $links = $xpath->query('//a[contains(@href, "/przepis/")]');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');

            if (str_contains($href, '#')) {
                continue;
            }

            if (preg_match('#^/przepis/[\w-]+$#', $href)) {
                $fullUrl = self::BASE_URL . $href;
                if (!in_array($fullUrl, $urls)) {
                    $urls[] = $fullUrl;
                }
            }
        }

        return $urls;
    }

    public function getLastPageNumber(string $html): int
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        $paginationLinks = $xpath->query('//ul[contains(@class, "pagination")]//a[contains(@href, "/strona/")]');

        $maxPage = 1;
        foreach ($paginationLinks as $link) {
            $href = $link->getAttribute('href');
            if (preg_match('#/strona/(\d+)#', $href, $matches)) {
                $pageNum = (int) $matches[1];
                if ($pageNum > $maxPage) {
                    $maxPage = $pageNum;
                }
            }
        }

        return $maxPage;
    }

    public function scrapeRecipe(string $url): ?array
    {
        $html = $this->fetchPage($url);
        if (!$html) {
            return null;
        }

        return $this->parseRecipe($html, $url);
    }

    public function parseRecipe(string $html, string $url): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        return [
            'name' => $this->extractText($xpath, '//h1[@itemprop="name"]'),
            'url' => $url,
            'author' => $this->parseAuthor($xpath),
            'published_at' => $this->parseDate($this->extractMeta($xpath, 'datePublished')),
            'modified_at' => $this->parseDate($this->extractMeta($xpath, 'dateModified')),
            'category' => $this->extractMeta($xpath, 'recipeCategory'),
            'cuisine' => $this->extractMeta($xpath, 'recipeCuisine'),
            'description' => $this->extractMeta($xpath, 'description'),
            'prep_time' => $this->parseIsoDuration($this->extractMetaContent($xpath, 'prepTime')),
            'cook_time' => $this->parseIsoDuration($this->extractMetaContent($xpath, 'cookTime')),
            'total_time' => $this->parseIsoDuration($this->extractMetaContent($xpath, 'totalTime')),
            'servings' => $this->extractMetaContent($xpath, 'recipeYield'),
            'nutrition' => $this->parseNutrition($xpath),
            'ingredients' => $this->parseIngredients($xpath),
            'steps' => $this->parseSteps($xpath),
            'images' => $this->parseImages($xpath),
            'rating_value' => $this->parseRatingValue($xpath),
            'rating_count' => $this->parseRatingCount($xpath),
            'comment_count' => $this->parseCommentCount($xpath),
            'diet' => $this->parseDiet($xpath),
            'keywords' => $this->parseKeywords($xpath),
        ];
    }

    private function extractText(DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }
        return null;
    }

    private function extractMeta(DOMXPath $xpath, string $itemprop): ?string
    {
        $nodes = $xpath->query("//meta[@itemprop='{$itemprop}']/@content");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->nodeValue);
        }
        return null;
    }

    private function extractMetaContent(DOMXPath $xpath, string $itemprop): ?string
    {
        return $this->extractMeta($xpath, $itemprop);
    }

    private function parseIsoDuration(?string $iso): ?string
    {
        if (!$iso) {
            return null;
        }

        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?/', $iso, $matches)) {
            $hours = isset($matches[1]) ? (int) $matches[1] : 0;
            $minutes = isset($matches[2]) ? (int) $matches[2] : 0;

            $parts = [];
            if ($hours > 0) {
                $parts[] = "{$hours} " . ($hours === 1 ? 'godzina' : ($hours < 5 ? 'godziny' : 'godzin'));
            }
            if ($minutes > 0) {
                $parts[] = "{$minutes} " . ($minutes === 1 ? 'minuta' : ($minutes < 5 ? 'minuty' : 'minut'));
            }

            return implode(' ', $parts) ?: null;
        }

        return null;
    }

    private function parseNutrition(DOMXPath $xpath): ?array
    {
        $nutrition = [];

        $caloriesNode = $xpath->query('//*[@itemprop="calories"]');
        if ($caloriesNode->length > 0) {
            $nutrition['calories'] = trim($caloriesNode->item(0)->textContent);
        }

        $carbsNode = $xpath->query('//*[@itemprop="carbohydrateContent"]');
        if ($carbsNode->length > 0) {
            $nutrition['carbs'] = trim($carbsNode->item(0)->textContent);
        }

        $sugarNode = $xpath->query('//*[@itemprop="sugarContent"]');
        if ($sugarNode->length > 0) {
            $nutrition['sugar'] = trim($sugarNode->item(0)->textContent);
        }

        $proteinNode = $xpath->query('//*[@itemprop="proteinContent"]');
        if ($proteinNode->length > 0) {
            $nutrition['protein'] = trim($proteinNode->item(0)->textContent);
        }

        $fatNode = $xpath->query('//*[@itemprop="fatContent"]');
        if ($fatNode->length > 0) {
            $nutrition['fat'] = trim($fatNode->item(0)->textContent);
        }

        return !empty($nutrition) ? $nutrition : null;
    }

    private function parseIngredients(DOMXPath $xpath): array
    {
        $ingredients = [];
        $currentSection = null;
        $currentItems = [];

        $container = $xpath->query('//*[@id="recipeIngredients"]');
        if ($container->length === 0) {
            return $ingredients;
        }

        // Get section headers and ingredient list items
        $headers = $xpath->query('//*[@id="recipeIngredients"]//p[contains(@class, "ing-header")]');
        $lists = $xpath->query('//*[@id="recipeIngredients"]//ul[contains(@class, "recipe-ing-list")]');

        // If there are multiple headers, match them with lists
        if ($headers->length > 0) {
            foreach ($headers as $index => $header) {
                $sectionName = trim($header->textContent);
                $items = [];

                // Get the corresponding list (same index)
                if ($lists->length > $index) {
                    $list = $lists->item($index);
                    $liNodes = $xpath->query('.//li', $list);

                    foreach ($liNodes as $li) {
                        $nameNode = $xpath->query('.//span[contains(@class, "ingredient")]', $li);
                        $qtyNode = $xpath->query('.//span[contains(@class, "qty")]', $li);

                        $name = $nameNode->length > 0 ? trim($nameNode->item(0)->textContent) : null;
                        $qty = $qtyNode->length > 0 ? trim($qtyNode->item(0)->textContent) : null;

                        if ($name) {
                            $items[] = ['name' => $name, 'qty' => $qty];
                        }
                    }
                }

                if (!empty($items)) {
                    $ingredients[] = [
                        'section' => $sectionName,
                        'items' => $items,
                    ];
                }
            }
        } else {
            // No sections, just parse all li items
            $liNodes = $xpath->query('//*[@id="recipeIngredients"]//ul//li');
            foreach ($liNodes as $li) {
                $nameNode = $xpath->query('.//span[contains(@class, "ingredient")]', $li);
                $qtyNode = $xpath->query('.//span[contains(@class, "qty")]', $li);

                $name = $nameNode->length > 0 ? trim($nameNode->item(0)->textContent) : null;
                $qty = $qtyNode->length > 0 ? trim($qtyNode->item(0)->textContent) : null;

                if ($name) {
                    $currentItems[] = ['name' => $name, 'qty' => $qty];
                }
            }

            if (!empty($currentItems)) {
                $ingredients[] = [
                    'section' => null,
                    'items' => $currentItems,
                ];
            }
        }

        return $ingredients;
    }

    private function parseSteps(DOMXPath $xpath): array
    {
        $steps = [];

        // Try new format first: multiple divs with itemprop="recipeInstructions"
        $stepNodes = $xpath->query('//div[@itemprop="recipeInstructions"]');

        if ($stepNodes->length > 0) {
            // Check if this is the new format (has position/name/text meta) or old format (inline paragraphs)
            $firstStep = $stepNodes->item(0);
            $hasPositionMeta = $xpath->query('.//meta[@itemprop="position"]', $firstStep)->length > 0;

            if ($hasPositionMeta) {
                // New format: each div is a separate step with position, name, text
                foreach ($stepNodes as $stepNode) {
                    $positionNode = $xpath->query('.//meta[@itemprop="position"]/@content', $stepNode);
                    $position = $positionNode->length > 0 ? (int) $positionNode->item(0)->nodeValue : count($steps) + 1;

                    $nameNode = $xpath->query('.//span[@itemprop="name"]', $stepNode);
                    $name = $nameNode->length > 0 ? trim($nameNode->item(0)->textContent) : null;

                    $textNode = $xpath->query('.//div[@itemprop="text"]', $stepNode);
                    $text = '';
                    if ($textNode->length > 0) {
                        $paragraphs = $xpath->query('.//p', $textNode->item(0));
                        $textParts = [];
                        foreach ($paragraphs as $p) {
                            $textParts[] = trim($p->textContent);
                        }
                        $text = implode("\n", $textParts);
                    }

                    $imageNode = $xpath->query('.//img[@itemprop="image"]/@src', $stepNode);
                    $image = $imageNode->length > 0 ? trim($imageNode->item(0)->nodeValue) : null;

                    $steps[] = [
                        'step' => $position,
                        'name' => $name,
                        'text' => $text,
                        'image' => $image,
                    ];
                }
            } else {
                // Old format: single div with class "article-content-body" containing paragraphs
                // Skip intro paragraph (inside .article-intro) and extract step paragraphs
                $steps = $this->parseOldFormatSteps($xpath, $firstStep);
            }
        }

        return $steps;
    }

    private function parseOldFormatSteps(DOMXPath $xpath, \DOMNode $container): array
    {
        $steps = [];

        // Get all direct children and siblings that are paragraphs with step content
        // Skip: .article-intro, .recipe-info, #recipeIngredients, .copy-share-lock-con, .ads-slot-article
        $paragraphs = $xpath->query('.//p[not(ancestor::div[contains(@class, "article-intro")]) and not(ancestor::p[contains(@class, "recipe-info")]) and not(ancestor::div[@id="recipeIngredients"]) and not(ancestor::div[contains(@class, "copy-share-lock-con")]) and not(ancestor::div[contains(@class, "ads-slot-article")]) and not(contains(@class, "recipe-info")) and not(contains(@class, "ing-header"))]', $container);

        $stepNumber = 1;
        foreach ($paragraphs as $p) {
            $text = trim($p->textContent);

            // Skip empty paragraphs or those that are just whitespace
            if (empty($text) || mb_strlen($text) < 10) {
                continue;
            }

            // Skip paragraphs that look like ingredient info or metadata
            if (preg_match('/^(Czas|Liczba porcji|Dieta|Składniki)/u', $text)) {
                continue;
            }

            // Find any image that follows this paragraph (in the next sibling img-placeholder div)
            $image = null;
            $nextSibling = $p->nextSibling;
            while ($nextSibling !== null) {
                if ($nextSibling->nodeType === XML_ELEMENT_NODE) {
                    if ($nextSibling->nodeName === 'div' && strpos($nextSibling->getAttribute('class') ?? '', 'img-placeholder') !== false) {
                        $imgNode = $xpath->query('.//img/@src', $nextSibling);
                        if ($imgNode->length > 0) {
                            $image = trim($imgNode->item(0)->nodeValue);
                        }
                        break;
                    } elseif ($nextSibling->nodeName === 'p') {
                        // Next paragraph found, stop looking for image
                        break;
                    }
                }
                $nextSibling = $nextSibling->nextSibling;
            }

            $steps[] = [
                'step' => $stepNumber,
                'name' => null,
                'text' => $text,
                'image' => $image,
            ];
            $stepNumber++;
        }

        return $steps;
    }

    private function parseImages(DOMXPath $xpath): ?array
    {
        $images = [];

        $imageNodes = $xpath->query('//meta[@itemprop="image"]/@content');

        foreach ($imageNodes as $node) {
            $url = trim($node->nodeValue);
            if ($url && !in_array($url, $images)) {
                $images[] = $url;
            }
        }

        return !empty($images) ? $images : null;
    }

    private function parseDiet(DOMXPath $xpath): ?string
    {
        $dietNode = $xpath->query('//link[@itemprop="suitableForDiet"]/@href');

        if ($dietNode->length > 0) {
            $href = $dietNode->item(0)->nodeValue;
            if (preg_match('#/([^/]+)Diet$#', $href, $matches)) {
                $dietName = $matches[1];
                $translations = [
                    'GlutenFree' => 'bezglutenowa',
                    'Vegetarian' => 'wegetariańska',
                    'Vegan' => 'wegańska',
                    'LowCalorie' => 'niskokaloryczna',
                    'LowFat' => 'niskotłuszczowa',
                    'LowCarb' => 'niskowęglowodanowa',
                ];
                return $translations[$dietName] ?? $dietName;
            }
        }

        return null;
    }

    private function parseAuthor(DOMXPath $xpath): ?string
    {
        $authorNode = $xpath->query('//div[@itemprop="author"]//meta[@itemprop="name"]/@content');
        if ($authorNode->length > 0) {
            return trim($authorNode->item(0)->nodeValue);
        }

        return null;
    }

    private function parseDate(?string $dateString): ?string
    {
        if (!$dateString) {
            return null;
        }

        try {
            $date = new \DateTime($dateString);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseRatingValue(DOMXPath $xpath): ?float
    {
        $ratingNode = $xpath->query('//*[@itemprop="ratingValue"]');
        if ($ratingNode->length > 0) {
            $value = trim($ratingNode->item(0)->textContent);
            return is_numeric($value) ? (float) $value : null;
        }

        return null;
    }

    private function parseRatingCount(DOMXPath $xpath): ?int
    {
        $countNode = $xpath->query('//*[@itemprop="ratingCount"]');
        if ($countNode->length > 0) {
            $value = trim($countNode->item(0)->textContent);
            return is_numeric($value) ? (int) $value : null;
        }

        return null;
    }

    private function parseCommentCount(DOMXPath $xpath): ?int
    {
        $countNode = $xpath->query('//meta[@itemprop="commentCount"]/@content');
        if ($countNode->length > 0) {
            $value = trim($countNode->item(0)->nodeValue);
            return is_numeric($value) ? (int) $value : null;
        }

        return null;
    }

    private function parseKeywords(DOMXPath $xpath): ?array
    {
        $keywordsNode = $xpath->query('//meta[@itemprop="keywords"]/@content');
        if ($keywordsNode->length > 0) {
            $keywords = trim($keywordsNode->item(0)->nodeValue);
            if ($keywords) {
                return array_map('trim', explode(',', $keywords));
            }
        }

        return null;
    }
}
