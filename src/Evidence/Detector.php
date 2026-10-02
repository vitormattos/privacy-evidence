<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence;

use PrivacyEvidence\Acquisition\FetchedDocument;

interface Detector
{
    public function name(): string;

    public function version(): string;

    /**
     * @return list<PrivacyEvidence>
     */
    public function detect(FetchedDocument $document): array;
}
