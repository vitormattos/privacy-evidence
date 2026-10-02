<?php

declare(strict_types=1);

namespace PrivacyEvidence\Browser;

interface BrowserProvider
{
    /**
     * @param list<array{action:string, selector?:string}> $actions
     */
    public function observe(string $url, array $actions = []): BrowserObservation;
}
