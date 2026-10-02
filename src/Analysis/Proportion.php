<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

final readonly class Proportion
{
    public function __construct(
        public int $numerator,
        public int $denominator,
        public int $unknown = 0,
        public int $unavailable = 0,
        public int $excluded = 0,
    ) {
        if ($denominator < 0 || $numerator < 0 || $numerator > $denominator) {
            throw new \InvalidArgumentException('Invalid proportion counts.');
        }
    }

    public function value(): ?float
    {
        return $this->denominator === 0 ? null : $this->numerator / $this->denominator;
    }
}
