<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Experiment;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Experiment\Rubix\RubixSignalClassifier;

final class RubixSignalArtifactHardeningTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-ml-hardening-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $paths = glob($this->root . '/*');
        if ($paths !== false) {
            foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function testRejectsOversizedArtifactBeforeDeserialization(): void
    {
        $artifact = $this->root . '/oversized.rbx';
        $handle = fopen($artifact, 'wb');
        self::assertIsResource($handle);
        fseek($handle, RubixSignalClassifier::MAX_ARTIFACT_BYTES);
        fwrite($handle, 'x');
        fclose($handle);
        file_put_contents($artifact . '.metadata.json', '{"artifactSha256":"' . str_repeat('a', 64) . '"}');

        try {
            RubixSignalClassifier::load($artifact);
            self::fail('Expected oversized model artifact to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('size must be between', $exception->getMessage());
        }
    }

    public function testRejectsOversizedMetadataBeforeJsonParsing(): void
    {
        $artifact = $this->root . '/model.rbx';
        file_put_contents($artifact, 'small');
        $metadata = $artifact . '.metadata.json';
        $handle = fopen($metadata, 'wb');
        self::assertIsResource($handle);
        fseek($handle, RubixSignalClassifier::MAX_METADATA_BYTES);
        fwrite($handle, 'x');
        fclose($handle);

        try {
            RubixSignalClassifier::load($artifact);
            self::fail('Expected oversized metadata to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('metadata size must be between', $exception->getMessage());
        }
    }
}
