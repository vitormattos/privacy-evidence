<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Text;

interface TextClassifier
{
    public function train(array $documents): void;

    public function classify(string $text): TextClassificationResult;
}
