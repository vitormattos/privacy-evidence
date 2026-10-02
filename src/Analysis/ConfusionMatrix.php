<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

final readonly class ConfusionMatrix
{
    public function __construct(
        public int $truePositive,
        public int $falsePositive,
        public int $trueNegative,
        public int $falseNegative,
    ) {
    }

    public function precision(): ?float
    {
        $denominator = $this->truePositive + $this->falsePositive;
        return $denominator === 0 ? null : $this->truePositive / $denominator;
    }

    public function recall(): ?float
    {
        $denominator = $this->truePositive + $this->falseNegative;
        return $denominator === 0 ? null : $this->truePositive / $denominator;
    }

    public function f1(): ?float
    {
        $precision = $this->precision();
        $recall = $this->recall();
        if ($precision === null || $recall === null || ($precision + $recall) === 0.0) {
            return null;
        }

        return 2.0 * $precision * $recall / ($precision + $recall);
    }

    public function support(): int
    {
        return $this->truePositive + $this->falseNegative;
    }
}
