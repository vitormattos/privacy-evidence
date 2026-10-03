<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Text;

final readonly class TextClassificationResult
{
    /** @param array<string,float> $scores */
    public function __construct(
        public string $label,
        public array $scores,
        public string $classifier,
        public string $classifierVersion,
    ) {
    }
}
