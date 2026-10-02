<?php

declare(strict_types=1);

namespace PrivacyEvidence\Pipeline;

use PrivacyEvidence\Evidence\Detector;

final readonly class DetectorRegistry
{
    /**
     * @param list<Detector> $detectors
     */
    public function __construct(public array $detectors)
    {
    }
}
