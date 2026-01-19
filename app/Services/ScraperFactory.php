<?php

namespace App\Services;

use App\Services\Contracts\RecipeScraperInterface;
use InvalidArgumentException;

class ScraperFactory
{
    public const TYPE_ANIA_GOTUJE = 'ania-gotuje';
    public const TYPE_ZE_SMAKIEM_NA_TY = 'ze-smakiem-na-ty';
    public const TYPE_POPROSTUPYCHA = 'poprostupycha';
    public const TYPE_SMAKER = 'smaker';

    private array $scrapers = [];

    public function __construct(
        private AniaGotujeScraper $aniaGotujeScraper,
        private ZeSmakiemNatyScraper $zeSmakiemNatyScraper,
        private PoprostuPychaScraper $poprostuPychaScraper,
        private SmakerScraper $smakerScraper,
    ) {
        $this->scrapers = [
            self::TYPE_ANIA_GOTUJE => $this->aniaGotujeScraper,
            self::TYPE_ZE_SMAKIEM_NA_TY => $this->zeSmakiemNatyScraper,
            self::TYPE_POPROSTUPYCHA => $this->poprostuPychaScraper,
            self::TYPE_SMAKER => $this->smakerScraper,
        ];
    }

    public function getScraperByType(string $type): RecipeScraperInterface
    {
        if (!isset($this->scrapers[$type])) {
            throw new InvalidArgumentException("Unknown scraper type: {$type}");
        }

        return $this->scrapers[$type];
    }

    public function getScraperByUrl(string $url): RecipeScraperInterface
    {
        $type = $this->detectTypeFromUrl($url);
        return $this->getScraperByType($type);
    }

    public function detectTypeFromUrl(string $url): string
    {
        if (str_contains($url, 'aniagotuje.pl')) {
            return self::TYPE_ANIA_GOTUJE;
        }

        if (str_contains($url, 'zesmakiemnaty.pl')) {
            return self::TYPE_ZE_SMAKIEM_NA_TY;
        }

        if (str_contains($url, 'poprostupycha.com.pl')) {
            return self::TYPE_POPROSTUPYCHA;
        }

        if (str_contains($url, 'smaker.pl')) {
            return self::TYPE_SMAKER;
        }

        throw new InvalidArgumentException("Cannot detect scraper type for URL: {$url}");
    }

    public function getSupportedTypes(): array
    {
        return array_keys($this->scrapers);
    }
}
