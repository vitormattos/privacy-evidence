<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Experiment;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Experiment\Rubix\RubixTextFeaturePipeline;

final class RubixTextFeaturePipelinePersistenceTest extends TestCase
{
    public function testFittedPipelineRoundTripsWithoutRefitting(): void
    {
        $root = sys_get_temp_dir() . '/privacy-evidence-feature-pipeline-' . bin2hex(random_bytes(6));
        mkdir($root, 0700, true);
        $artifact = $root . '/pipeline.rbx';

        try {
            $pipeline = new RubixTextFeaturePipeline(16);
            $pipeline->fitTransform([
                'privacy personal data controller rights',
                'dados pessoais controlador direitos',
                'community events products',
            ]);

            $expected = $pipeline->transform(['privacy controller rights']);
            $metadata = $pipeline->metadata()->toArray();

            $pipeline->save($artifact);
            self::assertFileExists($artifact);

            $loaded = RubixTextFeaturePipeline::load($artifact);

            self::assertSame($metadata, $loaded->metadata()->toArray());
            self::assertSame($expected, $loaded->transform(['privacy controller rights']));
        } finally {
            if (is_file($artifact)) {
                unlink($artifact);
            }
            if (is_dir($root)) {
                rmdir($root);
            }
        }
    }
}
