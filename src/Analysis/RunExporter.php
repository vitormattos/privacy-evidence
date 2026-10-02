<?php

declare(strict_types=1);

namespace PrivacyEvidence\Analysis;

use PrivacyEvidence\Runtime\RuntimeContext;

final readonly class RunExporter
{
    public function __construct(private RuntimeContext $runtime)
    {
    }

    public function export(string $runId, string $directory): void
    {
        $run = $this->runtime->runs->get($runId);
        if ($run === null) {
            throw new \InvalidArgumentException(sprintf('Unknown run %s.', $runId));
        }

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create export directory.');
        }

        $this->json($directory . '/manifest.json', $run->toArray());
        $this->json($directory . '/resources.json', $this->runtime->observations->resourceRecords($runId));
        $this->json($directory . '/documents.json', $this->runtime->observations->documentRecords($runId));
        $this->json(
            $directory . '/evidence.json',
            array_map(
                static fn ($item): array => $item->toArray(),
                $this->runtime->observations->evidence($runId),
            ),
        );
        $this->json($directory . '/reviews.json', $this->runtime->reviews->decisions($runId));
        $this->json($directory . '/profiles.json', $this->runtime->observations->profileResults($runId));
        $this->json($directory . '/telemetry.json', $this->runtime->runs->telemetry($runId));
        $this->json($directory . '/counts.json', $this->runtime->observations->counts($runId));

        $counts = $this->runtime->observations->counts($runId);
        $report = sprintf(
            "# Privacy Evidence run %s\n\n"
            . "- Protocol: %s\n"
            . "- Git commit: %s\n"
            . "- Dataset SHA-256: %s\n"
            . "- Resources: %d\n"
            . "- Documents: %d\n"
            . "- Evidence items: %d\n"
            . "- Regulatory profile results: %d\n\n"
            . "This report summarizes publicly observable evidence. It is not a legal-compliance certification.\n",
            $run->id,
            $run->protocolVersion,
            $run->gitCommit,
            $run->datasetHash,
            $counts['resources'] ?? 0,
            $counts['documents'] ?? 0,
            $counts['evidence'] ?? 0,
            $counts['profile_results'] ?? 0,
        );
        if (file_put_contents($directory . '/report.md', $report, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write report.');
        }
    }

    /**
     * @param mixed $value
     */
    private function json(string $path, mixed $value): void
    {
        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Unable to write %s.', $path));
        }
    }
}
