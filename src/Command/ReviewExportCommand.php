<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReviewExportCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('review:export');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Export pending review evidence as reviewer-neutral JSON Lines.')
            ->addArgument('run-id', InputArgument::REQUIRED)
            ->addArgument('output', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runId = $input->getArgument('run-id');
        $path = $input->getArgument('output');

        if (!is_string($runId) || $runId === '' || !is_string($path) || $path === '') {
            return Command::INVALID;
        }

        $runtime = RuntimeFactory::create($this->projectRoot);
        if ($runtime->runs->get($runId) === null) {
            $output->writeln('<error>Unknown run.</error>');

            return Command::FAILURE;
        }

        $directory = dirname($path);
        if ($directory !== '.' && !is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create review export directory.');
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open review export.');
        }

        $count = 0;
        try {
            foreach ($runtime->reviews->pending($runId) as $row) {
                /** @var mixed $evidence */
                $evidence = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($evidence)) {
                    throw new \RuntimeException('Review payload is not an object.');
                }

                $record = [
                    'schemaVersion' => '1.0.0',
                    'runId' => $runId,
                    'evidenceId' => $row['evidence_id'],
                    'evidence' => $evidence,
                    'decision' => [
                        'type' => $evidence['type'] ?? null,
                        'state' => null,
                        'reviewerType' => 'human',
                        'reviewerId' => null,
                        'reviewedAt' => null,
                        'rationale' => null,
                    ],
                ];

                fwrite(
                    $handle,
                    json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL,
                );
                $count++;
            }
        } finally {
            fclose($handle);
        }

        $output->writeln((string) $count);

        return Command::SUCCESS;
    }
}
