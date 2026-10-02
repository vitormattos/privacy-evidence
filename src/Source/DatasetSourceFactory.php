<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

use PrivacyEvidence\Source\Csv\CsvSource;
use PrivacyEvidence\Source\Ipb\IpbAnuarioSource;
use PrivacyEvidence\Source\Json\JsonSource;

final class DatasetSourceFactory
{
    public static function fromPath(string $path): SourceAdapter
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => new CsvSource($path),
            'json' => new JsonSource($path),
            'html', 'htm' => IpbAnuarioSource::fromFile($path),
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported dataset/source extension: %s', $extension),
            ),
        };
    }
}
