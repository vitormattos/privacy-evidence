<?php

declare(strict_types=1);

namespace PrivacyEvidence\Run;

final class RunManifestWriter
{
    public function write(ResearchRun $run, string $path, RunStatus $status = RunStatus::Created): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create manifest directory.');
        }

        $json = json_encode(
            $run->toArray($status),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write ResearchRun manifest.');
        }
    }
}
