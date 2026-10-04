<?php

declare(strict_types=1);

namespace PrivacyEvidence\Acquisition;

final class AcquisitionException extends \RuntimeException
{
    public function __construct(
        public readonly string $url,
        public readonly string $category,
        public readonly bool $retryable,
        string $message,
    ) {
        parent::__construct($message);
    }
}
