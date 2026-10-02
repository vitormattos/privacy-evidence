<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

interface SourceAdapter
{
    public function sourceId(): string;

    /**
     * @return iterable<ImportedResource>
     */
    public function resources(): iterable;

    public function snapshot(): SourceSnapshot;
}
