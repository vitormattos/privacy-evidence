<?php

declare(strict_types=1);

namespace PrivacyEvidence\Browser;

use Symfony\Component\Process\Process;

final readonly class PlaywrightBrowserProvider implements BrowserProvider
{
    public function __construct(
        private string $workerPath,
        private int $timeoutSeconds = 45,
    ) {
    }

    public function observe(string $url, array $actions = []): BrowserObservation
    {
        if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Browser provider accepts only HTTP/HTTPS URLs.');
        }

        $process = new Process(['node', $this->workerPath]);
        $process->setTimeout($this->timeoutSeconds);
        $process->setInput(json_encode([
            'url' => $url,
            'actions' => $actions,
            'timeoutMs' => max(1, ($this->timeoutSeconds - 5) * 1000),
        ], JSON_THROW_ON_ERROR));
        $process->mustRun();

        /** @var mixed $decoded */
        $decoded = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)
            || !is_string($decoded['url'] ?? null)
            || !is_string($decoded['html'] ?? null)
            || !is_string($decoded['capturedAt'] ?? null)
            || !is_string($decoded['browserVersion'] ?? null)
        ) {
            throw new \RuntimeException('Browser worker returned an invalid observation.');
        }

        $metadata = [];
        foreach (['cookies', 'localStorage', 'sessionStorage', 'requests'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                $metadata[$key] = $decoded[$key];
            }
        }

        return new BrowserObservation(
            url: $decoded['url'],
            html: $decoded['html'],
            capturedAt: $decoded['capturedAt'],
            browserVersion: $decoded['browserVersion'],
            metadata: $metadata,
        );
    }
}
