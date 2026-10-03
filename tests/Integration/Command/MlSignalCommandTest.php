<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\MlInferSignalCommand;
use PrivacyEvidence\Command\MlTrainSignalCommand;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelMapping;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class MlSignalCommandTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-ml-cli-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function testTrainThenInferWithoutExternalServices(): void
    {
        [$partition, $manifest] = $this->fixtureDataset();
        $artifact = $this->root . '/model/controller.rbx';

        $train = new CommandTester(new MlTrainSignalCommand());
        self::assertSame(Command::SUCCESS, $train->execute([
            'partition' => $partition,
            'manifest' => $manifest,
            'signal' => 'controller_identity',
            'artifact' => $artifact,
            'trained-at' => '2026-10-03T12:00:00Z',
        ]));

        /** @var mixed $trainOutput */
        $trainOutput = json_decode($train->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($trainOutput);
        self::assertSame('experimental', $trainOutput['status'] ?? null);
        self::assertFileExists($artifact);

        $infer = new CommandTester(new MlInferSignalCommand());
        self::assertSame(Command::SUCCESS, $infer->execute([
            'artifact' => $artifact,
            'text' => 'controller legal company identity privacy notice',
            '--threshold' => '0.5',
        ]));

        /** @var mixed $inferOutput */
        $inferOutput = json_decode($infer->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($inferOutput);
        self::assertSame('experimental', $inferOutput['status'] ?? null);
        self::assertIsArray($inferOutput['prediction'] ?? null);
        self::assertSame(
            'controller_identity',
            $inferOutput['prediction']['signal'] ?? null,
        );
        self::assertSame(
            'fixture-v1',
            $inferOutput['prediction']['model']['datasetVersion'] ?? null,
        );
    }

    public function testTrainingRejectsManifestOutsideExternalDevelopmentPurpose(): void
    {
        [$partition, $manifest] = $this->fixtureDataset('final-evaluation-data');
        $artifact = $this->root . '/model/controller.rbx';

        $tester = new CommandTester(new MlTrainSignalCommand());

        self::assertSame(Command::INVALID, $tester->execute([
            'partition' => $partition,
            'manifest' => $manifest,
            'signal' => 'controller_identity',
            'artifact' => $artifact,
            'trained-at' => '2026-10-03T12:00:00Z',
        ]));
        self::assertStringContainsString('external-development-data', $tester->getDisplay());
        self::assertFileDoesNotExist($artifact);
    }

    public function testInferenceRejectsCorruptArtifact(): void
    {
        $artifact = $this->root . '/broken.rbx';
        file_put_contents($artifact, 'broken');

        $tester = new CommandTester(new MlInferSignalCommand());

        self::assertSame(Command::FAILURE, $tester->execute([
            'artifact' => $artifact,
            'text' => 'controller identity',
        ]));
        self::assertStringContainsString('metadata sidecar is missing', $tester->getDisplay());
    }

    /**
     * @return array{0:string,1:string}
     */
    private function fixtureDataset(string $purpose = 'external-development-data'): array
    {
        $partition = $this->root . '/train.jsonl';
        $manifest = $this->root . '/manifest.json';

        $positive = [
            'controller company identity privacy policy',
            'data controller legal name address',
            'responsável tratamento controlador empresa',
            'identificação controlador dados pessoais',
        ];
        $negative = [
            'community news events products',
            'welcome home services',
            'notícias comunidade agenda eventos',
            'produtos serviços novidades institucional',
        ];

        $lines = [];
        $counter = 1;
        foreach ($positive as $text) {
            $lines[] = $this->row($counter++, $text, ['Controller identification']);
        }
        foreach ($negative as $text) {
            $lines[] = $this->row($counter++, $text, ['Generic expressions']);
        }
        file_put_contents($partition, implode(PHP_EOL, $lines) . PHP_EOL);

        file_put_contents($manifest, json_encode([
            'datasetId' => 'fixture',
            'datasetVersion' => 'fixture-v1',
            'canonicalSha256' => str_repeat('a', 64),
            'purpose' => $purpose,
            'split' => [
                'splitSha256' => str_repeat('b', 64),
                'mappingVersion' => ClaudinhaLabelMapping::VERSION,
            ],
        ], JSON_THROW_ON_ERROR));

        return [$partition, $manifest];
    }

    /**
     * @param list<string> $labels
     */
    private function row(int $id, string $text, array $labels): string
    {
        return json_encode([
            'paragraphId' => 'p' . $id,
            'companyId' => 'company-' . $id,
            'text' => $text,
            'externalLabels' => $labels,
            'language' => 'pt-BR',
            'sourceRecordIds' => [(string) $id],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
