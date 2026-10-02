<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Analysis\DetectorEvaluator;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Review\ReviewDecision;
use PrivacyEvidence\Review\ReviewerType;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReviewEvaluateCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('review:evaluate');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Evaluate detectors against one explicitly selected human ground-truth reviewer.')
            ->addArgument('run-id', InputArgument::REQUIRED)
            ->addArgument('reviewer-id', InputArgument::REQUIRED)
            ->addArgument('gold-version', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runId = $input->getArgument('run-id');
        $reviewerId = $input->getArgument('reviewer-id');
        $goldVersion = $input->getArgument('gold-version');

        if (
            !is_string($runId) || $runId === ''
            || !is_string($reviewerId) || $reviewerId === ''
            || !is_string($goldVersion) || $goldVersion === ''
        ) {
            return Command::INVALID;
        }

        $runtime = RuntimeFactory::create($this->projectRoot);
        $run = $runtime->runs->get($runId);
        if ($run === null) {
            return Command::FAILURE;
        }

        $groundTruth = [];
        foreach ($runtime->reviews->decisions($runId) as $row) {
            if (($row['reviewerType'] ?? null) !== ReviewerType::Human->value) {
                continue;
            }
            if (($row['reviewerId'] ?? null) !== $reviewerId) {
                continue;
            }

            $evidenceId = $row['evidenceId'] ?? null;
            $typeRaw = $row['type'] ?? null;
            $stateRaw = $row['state'] ?? null;
            $reviewedAt = $row['reviewedAt'] ?? null;
            $rationale = $row['rationale'] ?? null;

            if (
                !is_string($evidenceId)
                || !is_string($typeRaw)
                || !is_string($stateRaw)
                || !is_string($reviewedAt)
                || !is_string($rationale)
            ) {
                continue;
            }

            $type = EvidenceType::tryFrom($typeRaw);
            $state = ObservationState::tryFrom($stateRaw);
            if ($type === null || $state === null) {
                continue;
            }

            $groundTruth[] = new ReviewDecision(
                $runId,
                $evidenceId,
                $type,
                $state,
                ReviewerType::Human,
                $reviewerId,
                $reviewedAt,
                $rationale,
            );
        }

        if ($groundTruth === []) {
            $output->writeln('<error>No human ground-truth decisions found for reviewer.</error>');

            return Command::FAILURE;
        }

        $result = (new DetectorEvaluator())->evaluate(
            $runtime->observations->evidence($runId),
            $groundTruth,
            $run->protocolVersion,
            $goldVersion,
        );

        $output->writeln(json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return Command::SUCCESS;
    }
}
