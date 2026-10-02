<?php

declare(strict_types=1);

namespace PrivacyEvidence\Browser;

final readonly class BrowserEscalationDecision
{
    public function __construct(
        public bool $required,
        public string $reason,
    ) {
    }
}
