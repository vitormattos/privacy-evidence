<?php

declare(strict_types=1);

namespace PrivacyEvidence\Runtime;

use Symfony\Component\Process\Process;

final class GitRevision
{
    public static function detect(string $projectRoot): string
    {
        $process = new Process(['git', 'rev-parse', 'HEAD'], $projectRoot);
        $process->setTimeout(5.0);
        $process->run();

        if (!$process->isSuccessful()) {
            return 'unknown';
        }

        return trim($process->getOutput()) ?: 'unknown';
    }
}
