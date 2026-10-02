<?php

declare(strict_types=1);

namespace PrivacyEvidence\Browser;

final readonly class BrowserObservation
{
    /**
     * @param array<string, scalar|array<array-key, scalar>|null> $metadata
     */
    public function __construct(
        public string $url,
        public string $html,
        public string $capturedAt,
        public string $browserVersion,
        public array $metadata = [],
    ) {
    }

    public function sha256(): string
    {
        return hash('sha256', $this->html);
    }
}
