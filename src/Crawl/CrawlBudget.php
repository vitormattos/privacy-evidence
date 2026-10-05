<?php

declare(strict_types=1);

namespace PrivacyEvidence\Crawl;

final readonly class CrawlBudget
{
    public function __construct(
        public int $maxPages = 20,
        public int $maxDepth = 3,
        public int $maxBytes = 5_000_000,
        public int $maxDurationSeconds = 0,
        public int $maxBrowserPages = 3,
        public int $minLinkPriority = 50,
    ) {
        foreach (
            [$maxPages, $maxDepth, $maxBytes, $maxDurationSeconds, $maxBrowserPages, $minLinkPriority] as $value
        ) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Crawl budget values cannot be negative.');
            }
        }

        if ($minLinkPriority > 100) {
            throw new \InvalidArgumentException('Minimum link priority cannot exceed 100.');
        }
    }
}
