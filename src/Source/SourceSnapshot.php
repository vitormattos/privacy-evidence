<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

final readonly class SourceSnapshot
{
    public function __construct(
        public string $sourceId,
        public string $capturedAt,
        public string $sha256,
        public string $mediaType,
        public string $location,
    ) {
    }
}
