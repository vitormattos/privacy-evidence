<?php

declare(strict_types=1);

namespace PrivacyEvidence\Crawl;

final readonly class CandidateUrl
{
    public function __construct(
        public string $url,
        public int $priority,
        public string $reason,
        public string $sourceUrl,
        public string $anchorText,
        public string $ruleVersion,
    ) {
    }
}
