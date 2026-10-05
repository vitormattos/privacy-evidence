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
        public readonly ?int $retryDelayMs = null,
    ) {
        parent::__construct($message);
    }
}
