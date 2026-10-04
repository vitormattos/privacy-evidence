<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

interface RemoteSourceProvider
{
    public function providerId(): string;

    public function mediaType(): string;

    /**
     * @return array<string,scalar|null>
     */
    public function provenance(): array;

    public function fetch(): string;

    public function openSnapshot(string $path): SourceAdapter;
}
