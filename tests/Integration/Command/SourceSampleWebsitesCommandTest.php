<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\SourceSampleWebsitesCommand;
use PrivacyEvidence\Source\DatasetProvenance;
use PrivacyEvidence\Source\DatasetSourceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SourceSampleWebsitesCommandTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-source-sample-' . bin2hex(random_bytes(6));
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

    public function testSamplingIsDeterministicAndProducesGenericProvenance(): void
    {
        $population = $this->root . '/population.csv';
        file_put_contents($population, implode(PHP_EOL, [
            'id,name,url',
            'a,Alpha,https://alpha.example',
            'b,Beta,https://beta.example',
            'c,Social,https://instagram.com/example',
            'd,Delta,https://delta.example',
        ]) . PHP_EOL);

        $first = $this->root . '/first.csv';
        $second = $this->root . '/second.csv';

        foreach ([$first, $second] as $output) {
            $tester = new CommandTester(new SourceSampleWebsitesCommand());
            self::assertSame(Command::SUCCESS, $tester->execute([
                'dataset' => $population,
                'output' => $output,
                '--limit' => '2',
                '--seed' => 'fixed-seed',
            ]));
        }

        self::assertSame(file_get_contents($first), file_get_contents($second));

        $source = DatasetSourceFactory::fromPath($first);
        self::assertCount(2, iterator_to_array($source->resources()));

        $provenance = DatasetProvenance::discover($first);
        self::assertNotNull($provenance);
        self::assertSame('privacy-evidence-source-sampler', $provenance->summary['producer']);
        self::assertSame(hash_file('sha256', $first), $provenance->summary['datasetSha256']);

        $document = json_decode(
            (string) file_get_contents($first . '.provenance.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($document);
        self::assertSame(hash_file('sha256', $population), $document['inputDataset']['sha256'] ?? null);
        self::assertSame('fixed-seed', $document['sampling']['seed'] ?? null);
        self::assertSame(3, $document['sampling']['eligible'] ?? null);
        self::assertSame(2, $document['sampling']['selected'] ?? null);
    }
}
