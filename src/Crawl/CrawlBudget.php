<?php

declare(strict_types=1);

namespace PrivacyEvidence\Crawl;

final readonly class CrawlBudget
{
    public function __construct(
        public int $maxPages = 20,
        public int $maxDepth = 3,
        public int $maxBytes = 5_000_000,
        public int $maxDurationSeconds = 60,
        public int $maxBrowserPages = 3,
    ) {
        foreach ([$maxPages, $maxDepth, $maxBytes, $maxDurationSeconds, $maxBrowserPages] as $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Crawl budget values cannot be negative.');
            }
        }
    }
}
