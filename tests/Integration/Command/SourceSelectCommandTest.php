<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\SourceSelectCommand;
use PrivacyEvidence\Source\DatasetProvenance;
use PrivacyEvidence\Source\DatasetSourceFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SourceSelectCommandTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-source-select-' . bin2hex(random_bytes(6));
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

    public function testSelectsAllResourcesOfRequestedTypeAndPreservesMetadata(): void
    {
        $population = $this->root . '/population.csv';
        file_put_contents($population, implode(PHP_EOL, [
            'id,name,url,state',
            'a,Alpha,https://alpha.example,RJ',
            'b,Beta,https://instagram.com/beta,SP',
            'c,Gamma,https://gamma.example,MG',
        ]) . PHP_EOL);

        $selected = $this->root . '/selected.csv';
        $tester = new CommandTester(new SourceSelectCommand());

        self::assertSame(Command::SUCCESS, $tester->execute([
            'dataset' => $population,
            'output' => $selected,
            '--type' => 'institutional_website',
        ]));

        $resources = iterator_to_array(DatasetSourceFactory::fromPath($selected)->resources());
        self::assertCount(2, $resources);
        self::assertSame(['Alpha', 'Gamma'], array_map(
            static fn ($resource): string => $resource->name,
            $resources,
        ));
        self::assertSame('RJ', $resources[0]->metadata['state'] ?? null);
        self::assertSame('MG', $resources[1]->metadata['state'] ?? null);

        $provenance = DatasetProvenance::discover($selected);
        self::assertNotNull($provenance);
        self::assertSame('privacy-evidence-source-selector', $provenance->summary['producer']);
    }

    public function testRejectsUnknownResourceType(): void
    {
        $population = $this->root . '/population.csv';
        file_put_contents($population, "id,name,url\na,Alpha,https://alpha.example\n");

        $tester = new CommandTester(new SourceSelectCommand());
        self::assertSame(Command::INVALID, $tester->execute([
            'dataset' => $population,
            'output' => $this->root . '/selected.csv',
            '--type' => 'not-a-type',
        ]));
    }
}
