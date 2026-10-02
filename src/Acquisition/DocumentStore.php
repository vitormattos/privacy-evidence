<?php

declare(strict_types=1);

namespace PrivacyEvidence\Acquisition;

interface DocumentStore
{
    public function put(FetchedDocument $document): string;

    public function get(string $sha256): ?string;
}
