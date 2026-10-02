<?php

declare(strict_types=1);

namespace PrivacyEvidence\Pipeline;

use PrivacyEvidence\Crawl\CrawlBudget;

final readonly class PipelineConfig
{
    public function __construct(
        public int $maxBodyBytes = 2_000_000,
        public int $maxJobsPerInvocation = 0,
        public bool $enableBrowserEscalation = true,
        public CrawlBudget $crawlBudget = new CrawlBudget(),
    ) {
        if ($maxBodyBytes <= 0 || $maxJobsPerInvocation < 0) {
            throw new \InvalidArgumentException('Invalid pipeline limits.');
        }
    }
}
