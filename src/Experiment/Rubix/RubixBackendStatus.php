<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Rubix;

final readonly class RubixBackendStatus
{
    public function __construct(
        public string $prediction,
        public string $engineVersion,
        public string $tensorVersion,
    ) {
    }
}
