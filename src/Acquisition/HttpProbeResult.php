<?php

declare(strict_types=1);

namespace PrivacyEvidence\Acquisition;

final readonly class HttpProbeResult
{
    /**
     * @param list<string> $redirectChain
     */
    public function __construct(
        public string $requestedUrl,
        public ?string $finalUrl,
        public ?int $statusCode,
        public ?string $contentType,
        public array $redirectChain = [],
        public ?ProbeFailure $failure = null,
        public ?string $failureDetail = null,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->failure === null && $this->statusCode !== null;
    }
}
