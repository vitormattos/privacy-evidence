<?php

declare(strict_types=1);

namespace PrivacyEvidence\Acquisition;

final readonly class FetchedDocument
{
    public string $sha256;

    public function __construct(
        public string $resourceId,
        public string $requestedUrl,
        public string $finalUrl,
        public int $statusCode,
        public string $mediaType,
        public string $body,
        public string $fetchedAt,
        public string $acquisitionMode = 'http',
    ) {
        $this->sha256 = hash('sha256', $body);
    }
}
