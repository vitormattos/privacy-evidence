<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Review\AgreementCalculator;
use PrivacyEvidence\Review\ReviewDecision;
use PrivacyEvidence\Review\ReviewerType;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReviewAgreementCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('review:agreement');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Calculate per-signal Cohen kappa for two independent human reviewers.')
            ->addArgument('run-id', InputArgument::REQUIRED)
            ->addArgument('reviewer-a', InputArgument::REQUIRED)
            ->addArgument('reviewer-b', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runId = $input->getArgument('run-id');
        $reviewerA = $input->getArgument('reviewer-a');
        $reviewerB = $input->getArgument('reviewer-b');

        if (
            !is_string($runId) || $runId === ''
            || !is_string($reviewerA) || $reviewerA === ''
            || !is_string($reviewerB) || $reviewerB === ''
            || $reviewerA === $reviewerB
        ) {
            return Command::INVALID;
        }

        $runtime = RuntimeFactory::create($this->projectRoot);
        if ($runtime->runs->get($runId) === null) {
            return Command::FAILURE;
        }

        $decisions = [];
        foreach ($runtime->reviews->decisions($runId) as $row) {
            $evidenceId = $row['evidenceId'] ?? null;
            $type = $row['type'] ?? null;
            $state = $row['state'] ?? null;
            $reviewerType = $row['reviewerType'] ?? null;
            $reviewerId = $row['reviewerId'] ?? null;
            $reviewedAt = $row['reviewedAt'] ?? null;
            $rationale = $row['rationale'] ?? null;

            if (
                !is_string($evidenceId)
                || !is_string($type)
                || !is_string($state)
                || !is_string($reviewerType)
                || !is_string($reviewerId)
                || !is_string($reviewedAt)
                || !is_string($rationale)
            ) {
                continue;
            }

            $typeEnum = EvidenceType::tryFrom($type);
            $stateEnum = ObservationState::tryFrom($state);
            $reviewerTypeEnum = ReviewerType::tryFrom($reviewerType);
            if ($typeEnum === null || $stateEnum === null || $reviewerTypeEnum === null) {
                continue;
            }

            $decisions[] = new ReviewDecision(
                $runId,
                $evidenceId,
                $typeEnum,
                $stateEnum,
                $reviewerTypeEnum,
                $reviewerId,
                $reviewedAt,
                $rationale,
            );
        }

        $result = (new AgreementCalculator())->cohenKappa($decisions, $reviewerA, $reviewerB);
        $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
