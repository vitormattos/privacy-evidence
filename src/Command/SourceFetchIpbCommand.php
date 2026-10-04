<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Source\Ipb\IpbAnuarioClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SourceFetchIpbCommand extends Command
{
    public function __construct(private readonly IpbAnuarioClient $client = new IpbAnuarioClient())
    {
        parent::__construct('source:fetch-ipb');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Download and preserve the current official IPB/iCalvinus church-directory snapshot.')
            ->addArgument('output', InputArgument::REQUIRED, 'Output HTML snapshot path.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('output');
        if (!is_string($path) || trim($path) === '') {
            return Command::INVALID;
        }

        try {
            $html = $this->client->download();
            $directory = dirname($path);
            if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Unable to create snapshot output directory.');
            }

            if (file_put_contents($path, $html, LOCK_EX) === false) {
                throw new \RuntimeException('Unable to write IPB source snapshot.');
            }

            $output->writeln(json_encode([
                'sourceId' => 'ipb-icalvinus',
                'endpoint' => IpbAnuarioClient::ENDPOINT,
                'output' => $path,
                'sha256' => hash('sha256', $html),
                'bytes' => strlen($html),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        } catch (\RuntimeException | \JsonException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
