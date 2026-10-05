<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class StatusCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('status');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Show durable status, queue counts, observations and pending reviews.')
            ->addArgument('run-id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $argument = $input->getArgument('run-id');
        if (!is_string($argument) || $argument === '') {
            $output->writeln('<error>Run id must be a non-empty string.</error>');

            return Command::INVALID;
        }

        $runId = $argument;
        $runtime = RuntimeFactory::create($this->projectRoot);
        $run = $runtime->runs->get($runId);

        if ($run === null) {
            $output->writeln(sprintf('<error>Unknown run %s</error>', $runId));

            return Command::FAILURE;
        }

        $status = [
            'runId' => $runId,
            'status' => ($runtime->runs->status($runId) ?? throw new \RuntimeException(
                sprintf('Run %s has no persisted status.', $runId),
            ))->value,
            'configuration' => $run->configuration,
            'jobs' => $runtime->jobs->counts($runId),
            'observations' => $runtime->observations->counts($runId),
            'telemetry' => $runtime->runs->telemetry($runId),
            'failures' => $runtime->jobs->failures($runId),
            'pendingReviews' => count($runtime->reviews->pending($runId)),
            'events' => count($runtime->runs->events($runId)),
        ];

        $output->writeln(
            json_encode($status, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );

        return Command::SUCCESS;
    }
}
