<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Text;

final readonly class TextClassificationResult
{
    public function __construct(
        public string $label,
        public array $scores,
        public string $classifier,
        public string $classifierVersion,
    ) {
    }
}
