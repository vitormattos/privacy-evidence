<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Examples;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Source\DatasetProvenance;
use PrivacyEvidence\Source\DatasetSourceFactory;
use PrivacyEvidence\Source\ResourceType;
use Symfony\Component\Process\Process;

final class IpbExternalSourceScriptTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-ipb-example-' . bin2hex(random_bytes(6));
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

    public function testExternalExampleProducesCanonicalDatasetAcceptedByCore(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $script = $projectRoot . '/examples/hacktoberfest/ipb/source.php';
        $fixture = $projectRoot . '/examples/hacktoberfest/ipb/fixtures/anuario-minimal.html';
        $dataset = $this->root . '/population.csv';

        $process = new Process([
            PHP_BINARY,
            $script,
            'extract',
            $fixture,
            $dataset,
        ]);
        $process->setTimeout(10.0);
        $process->run();

        self::assertTrue($process->isSuccessful(), $process->getErrorOutput());
        self::assertFileExists($dataset);
        self::assertFileExists($dataset . '.provenance.json');

        $source = DatasetSourceFactory::fromPath($dataset);
        $resources = iterator_to_array($source->resources());

        self::assertCount(1, $resources);
        self::assertSame('IGREJA PRESBITERIANA TESTE', $resources[0]->name);
        self::assertSame('https://example.test/', $resources[0]->normalizedUrl);
        self::assertSame(ResourceType::InstitutionalWebsite, $resources[0]->type);
        self::assertSame('PRTB', $resources[0]->metadata['presbytery'] ?? null);
        self::assertSame('Rio de Janeiro', $resources[0]->metadata['municipality'] ?? null);
        self::assertSame('RJ', $resources[0]->metadata['state'] ?? null);

        $provenance = DatasetProvenance::discover($dataset);
        self::assertNotNull($provenance);
        self::assertSame('privacy-evidence-example-ipb-directory', $provenance->summary['producer']);
        self::assertSame(hash_file('sha256', $dataset), $provenance->summary['datasetSha256']);
    }

    public function testCoreRejectsTamperedExternallyProducedDataset(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $script = $projectRoot . '/examples/hacktoberfest/ipb/source.php';
        $fixture = $projectRoot . '/examples/hacktoberfest/ipb/fixtures/anuario-minimal.html';
        $dataset = $this->root . '/population.csv';

        $process = new Process([PHP_BINARY, $script, 'extract', $fixture, $dataset]);
        $process->setTimeout(10.0);
        $process->mustRun();

        file_put_contents($dataset, "tampered\n", FILE_APPEND);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match');

        DatasetProvenance::discover($dataset);
    }
}
