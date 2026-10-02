<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Analysis\InterRaterAgreement;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
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
            ->setDescription('Calculate per-signal agreement between two human reviewers.')
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
            $output->writeln('<error>Unknown run.</error>');

            return Command::FAILURE;
        }

        $decisions = [];
        foreach ($runtime->reviews->decisions($runId) as $row) {
            $reviewerId = $row['reviewerId'] ?? null;
            $reviewerType = $row['reviewerType'] ?? null;
            if (
                !is_string($reviewerId)
                || !in_array($reviewerId, [$reviewerA, $reviewerB], true)
                || $reviewerType !== ReviewerType::Human->value
            ) {
                continue;
            }

            $decisions[] = new ReviewDecision(
                runId: $runId,
                evidenceId: $this->string($row, 'evidenceId'),
                type: EvidenceType::from($this->string($row, 'type')),
                state: ObservationState::from($this->string($row, 'state')),
                reviewerType: ReviewerType::Human,
                reviewerId: $reviewerId,
                reviewedAt: $this->string($row, 'reviewedAt'),
                rationale: $this->string($row, 'rationale'),
            );
        }

        $result = (new InterRaterAgreement())->compare($decisions, $reviewerA, $reviewerB);
        $output->writeln(json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Review decision is missing %s.', $key));
        }

        return $value;
    }
}
