<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Command\SourceFetchCommand;
use PrivacyEvidence\Source\DatasetSourceFactory;
use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\RemoteSourceProvider;
use PrivacyEvidence\Source\SourceAdapter;
use PrivacyEvidence\Source\SourceProviderRegistry;
use PrivacyEvidence\Source\SourceSnapshot;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SourceProviderCommandTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/privacy-evidence-source-provider-' . bin2hex(random_bytes(6));
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

    public function testGenericFetchPersistsProviderMetadataAndFactoryReopensSnapshot(): void
    {
        $provider = $this->provider();
        $registry = SourceProviderRegistry::fromProviders([$provider]);
        $snapshot = $this->root . '/fixture.snapshot';

        $tester = new CommandTester(new SourceFetchCommand($registry));

        self::assertSame(Command::SUCCESS, $tester->execute([
            'provider' => 'fixture-provider',
            'output' => $snapshot,
        ]));

        self::assertSame('fixture body', file_get_contents($snapshot));
        self::assertFileExists($snapshot . '.source.json');

        $metadata = json_decode(
            (string) file_get_contents($snapshot . '.source.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($metadata);
        self::assertSame('fixture-provider', $metadata['providerId'] ?? null);
        self::assertSame(hash('sha256', 'fixture body'), $metadata['sha256'] ?? null);

        $source = DatasetSourceFactory::fromPath($snapshot, $registry);
        self::assertSame('fixture-source', $source->sourceId());
        self::assertSame(hash('sha256', 'fixture body'), $source->snapshot()->sha256);
    }

    public function testUnknownProviderFailsWithoutWritingSnapshot(): void
    {
        $registry = SourceProviderRegistry::fromProviders([]);
        $snapshot = $this->root . '/missing.snapshot';
        $tester = new CommandTester(new SourceFetchCommand($registry));

        self::assertSame(Command::FAILURE, $tester->execute([
            'provider' => 'missing',
            'output' => $snapshot,
        ]));
        self::assertStringContainsString('Unknown source provider', $tester->getDisplay());
        self::assertFileDoesNotExist($snapshot);
    }

    public function testProviderSnapshotChecksumIsVerifiedBeforeParsing(): void
    {
        $provider = $this->provider();
        $registry = SourceProviderRegistry::fromProviders([$provider]);
        $snapshot = $this->root . '/fixture.snapshot';
        $tester = new CommandTester(new SourceFetchCommand($registry));

        self::assertSame(Command::SUCCESS, $tester->execute([
            'provider' => 'fixture-provider',
            'output' => $snapshot,
        ]));

        file_put_contents($snapshot, 'tampered');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('checksum');

        DatasetSourceFactory::fromPath($snapshot, $registry);
    }

    private function provider(): RemoteSourceProvider
    {
        return new class implements RemoteSourceProvider {
            public function providerId(): string
            {
                return 'fixture-provider';
            }

            public function mediaType(): string
            {
                return 'text/plain';
            }

            public function provenance(): array
            {
                return ['fixture' => true];
            }

            public function fetch(): string
            {
                return 'fixture body';
            }

            public function openSnapshot(string $path): SourceAdapter
            {
                return new class ($path) implements SourceAdapter {
                    public function __construct(private readonly string $path)
                    {
                    }

                    public function sourceId(): string
                    {
                        return 'fixture-source';
                    }

                    /**
                     * @return iterable<ImportedResource>
                     */
                    public function resources(): iterable
                    {
                        return [];
                    }

                    public function snapshot(): SourceSnapshot
                    {
                        $body = file_get_contents($this->path);
                        if (!is_string($body)) {
                            throw new \RuntimeException('Fixture snapshot unavailable.');
                        }

                        return new SourceSnapshot(
                            sourceId: $this->sourceId(),
                            capturedAt: '2026-10-04T00:00:00Z',
                            sha256: hash('sha256', $body),
                            mediaType: 'text/plain',
                            location: $this->path,
                        );
                    }
                };
            }
        };
    }
}
