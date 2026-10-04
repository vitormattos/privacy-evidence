<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Source\SourceProviderRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SourceFetchCommand extends Command
{
    private readonly SourceProviderRegistry $providers;

    public function __construct(?SourceProviderRegistry $providers = null)
    {
        $this->providers = $providers ?? SourceProviderRegistry::defaults();
        parent::__construct('source:fetch');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Download and preserve a registered remote source snapshot.')
            ->addArgument('provider', InputArgument::REQUIRED, 'Registered source provider id.')
            ->addArgument('output', InputArgument::REQUIRED, 'Output snapshot path.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $providerId = $input->getArgument('provider');
        $path = $input->getArgument('output');

        if (!is_string($providerId) || trim($providerId) === '' || !is_string($path) || trim($path) === '') {
            return Command::INVALID;
        }

        try {
            $provider = $this->providers->get($providerId);
            $body = $provider->fetch();
            $directory = dirname($path);
            if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create snapshot output directory.');
            }

            if (file_put_contents($path, $body, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to write source snapshot.');
            }

            $metadata = [
                'schemaVersion' => '1.0.0',
                'providerId' => $provider->providerId(),
                'mediaType' => $provider->mediaType(),
                'sha256' => hash('sha256', $body),
                'bytes' => strlen($body),
                'provenance' => $provider->provenance(),
            ];

            if (file_put_contents(
                $path . '.source.json',
                json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
                LOCK_EX,
            ) === false) {
                throw new \RuntimeException('Unable to write source snapshot metadata.');
            }

            $output->writeln(json_encode(
                $metadata + ['output' => $path],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ));

            return Command::SUCCESS;
        } catch (\InvalidArgumentException | \RuntimeException | \JsonException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
