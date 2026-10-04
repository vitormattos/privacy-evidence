<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

use PrivacyEvidence\Source\Csv\CsvSource;
use PrivacyEvidence\Source\Json\JsonSource;

final class DatasetSourceFactory
{
    public static function fromPath(
        string $path,
        ?SourceProviderRegistry $providers = null,
    ): SourceAdapter {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            return new CsvSource($path);
        }

        if ($extension === 'json') {
            return new JsonSource($path);
        }

        $metadataPath = $path . '.source.json';
        if (!is_file($metadataPath)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unsupported dataset/source extension or missing provider metadata: %s',
                    $extension,
                ),
            );
        }

        $contents = file_get_contents($metadataPath);
        if (!is_string($contents)) {
            throw new \InvalidArgumentException('Unable to read source provider metadata.');
        }

        $metadata = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($metadata)) {
            throw new \InvalidArgumentException('Source provider metadata must decode to an object.');
        }

        $providerId = $metadata['providerId'] ?? null;
        if (!is_string($providerId) || $providerId === '') {
            throw new \InvalidArgumentException('Source provider metadata has no providerId.');
        }

        $expectedSha256 = $metadata['sha256'] ?? null;
        if (!is_string($expectedSha256) || preg_match('/^[a-f0-9]{64}$/D', $expectedSha256) !== 1) {
            throw new \InvalidArgumentException('Source provider metadata has an invalid SHA-256.');
        }

        $actualSha256 = hash_file('sha256', $path);
        if (!is_string($actualSha256) || !hash_equals($expectedSha256, $actualSha256)) {
            throw new \InvalidArgumentException('Source snapshot checksum does not match provider metadata.');
        }

        return ($providers ?? SourceProviderRegistry::defaults())
            ->get($providerId)
            ->openSnapshot($path);
    }
}
