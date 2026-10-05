<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Review;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Review\ReviewMaterial;

final class ReviewMaterialTest extends TestCase
{
    public function testPreservesFullContextAndSampleIdentityWithoutChangingDetectorEvidence(): void
    {
        $directory = sys_get_temp_dir() . '/review-material-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        $body = '<html><head><title>Privacy policy</title><script>DO_NOT_EXECUTE</script></head>'
            . '<body><p>Controlador de dados is a generic term.</p>'
            . '<p>Our controller is Synthetic Example Ltd.</p><a href="mailto:privacy@example.test">Contact</a></body></html>';
        $hash = hash('sha256', $body);
        file_put_contents($directory . '/' . $hash . '.bin', $body);
        $resources = [['id' => 'sample-1', 'name' => 'Sample website', 'normalizedUrl' => 'https://sample.test/']];
        $documents = [[
            'artifactHash' => $hash, 'resourceId' => 'sample-1', 'finalUrl' => 'https://external.test/policy',
            'fetchedAt' => '2026-10-05T00:00:00Z', 'mediaType' => 'text/html', 'truncated' => true,
        ]];
        $case = [
            'resourceId' => 'sample-1', 'artifactHash' => $hash, 'sourceUrl' => 'https://external.test/policy',
            'evidenceType' => 'controller_identity', 'excerpt' => 'controlador de dados', 'automatedState' => 'present',
        ];
        try {
            $builder = new ReviewMaterial($resources, $documents, $directory);
            $package = $builder->enrich(['cases' => [$case, $case + ['evidenceId' => 'another']]]);
            self::assertSame('1.1.0', $package['packageVersion']);
            /** @var list<array<string, mixed>> $cases */
            $cases = $package['cases'];
            /** @var array<string, mixed> $context */
            $context = $cases[0]['reviewContext'];
            self::assertSame('Sample website', $context['resourceName']);
            self::assertSame('https://sample.test/', $context['resourceUrl']);
            self::assertSame('2026-10-05T00:00:00Z', $context['fetchedAt']);
            self::assertTrue($context['truncated']);
            self::assertNull($context['reason']);
            self::assertSame('controlador de dados', $cases[0]['excerpt']);
            /** @var array<string, array{title: string, text: string}> $material */
            $material = $package['reviewDocuments'];
            self::assertCount(1, $material);
            self::assertSame('Privacy policy', $material[$hash]['title']);
            self::assertStringContainsString('Synthetic Example Ltd.', $material[$hash]['text']);
            self::assertStringContainsString('mailto:privacy@example.test', $material[$hash]['text']);
            self::assertStringNotContainsString('DO_NOT_EXECUTE', $material[$hash]['text']);

            $blank = $case;
            $blank['excerpt'] = null;
            $prepared = $builder->enrich(['cases' => [$blank]]);
            /** @var list<array{reviewContext: array{reason: ?string}}> $blankCases */
            $blankCases = $prepared['cases'];
            self::assertNull($blankCases[0]['reviewContext']['reason']);

            $behavior = $case;
            $behavior['evidenceType'] = 'third_party_requests_before_consent';
            $prepared = $builder->enrich(['cases' => [$behavior]]);
            /** @var list<array{reviewContext: array{reason: ?string}}> $behaviorCases */
            $behaviorCases = $prepared['cases'];
            self::assertSame('behavioral_material_required', $behaviorCases[0]['reviewContext']['reason']);

            file_put_contents($directory . '/' . $hash . '.bin', 'corrupt');
            $prepared = $builder->enrich(['cases' => [$case]]);
            /** @var list<array{reviewContext: array{reason: ?string}}> $corruptCases */
            $corruptCases = $prepared['cases'];
            self::assertSame('hash_mismatch', $corruptCases[0]['reviewContext']['reason']);
            unlink($directory . '/' . $hash . '.bin');
            $prepared = $builder->enrich(['cases' => [$case]]);
            /** @var list<array{reviewContext: array{reason: ?string}}> $missingCases */
            $missingCases = $prepared['cases'];
            self::assertSame('missing_artifact', $missingCases[0]['reviewContext']['reason']);
        } finally {
            if (is_file($directory . '/' . $hash . '.bin')) {
                unlink($directory . '/' . $hash . '.bin');
            }
            rmdir($directory);
        }
    }
}
