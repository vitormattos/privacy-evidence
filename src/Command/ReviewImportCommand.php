<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Review\ReviewDecision;
use PrivacyEvidence\Review\ReviewerType;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ReviewImportCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('review:import');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Import completed review decisions from JSON Lines.')
            ->addArgument('input', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('input');
        if (!is_string($path) || $path === '' || !is_file($path)) {
            $output->writeln('<error>Review input file does not exist.</error>');

            return Command::INVALID;
        }

        $runtime = RuntimeFactory::create($this->projectRoot);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open review input.');
        }

        $count = 0;
        try {
            while (($line = fgets($handle)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                /** @var mixed $record */
                $record = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                if (!is_array($record)) {
                    throw new \InvalidArgumentException('Review row must be a JSON object.');
                }

                $decision = $record['decision'] ?? null;
                if (!is_array($decision)) {
                    throw new \InvalidArgumentException('Review row is missing decision.');
                }

                $runId = $this->requiredString($record, 'runId');
                if ($runtime->runs->get($runId) === null) {
                    throw new \InvalidArgumentException(sprintf('Unknown run %s.', $runId));
                }

                $runtime->reviews->decide(new ReviewDecision(
                    runId: $runId,
                    evidenceId: $this->requiredString($record, 'evidenceId'),
                    type: EvidenceType::from($this->requiredString($decision, 'type')),
                    state: ObservationState::from($this->requiredString($decision, 'state')),
                    reviewerType: ReviewerType::from($this->requiredString($decision, 'reviewerType')),
                    reviewerId: $this->requiredString($decision, 'reviewerId'),
                    reviewedAt: $this->requiredString($decision, 'reviewedAt'),
                    rationale: $this->requiredString($decision, 'rationale'),
                ));
                $count++;
            }
        } finally {
            fclose($handle);
        }

        $output->writeln((string) $count);

        return Command::SUCCESS;
    }

    /**
     * @param array<array-key,mixed> $record
     */
    private function requiredString(array $record, string $key): string
    {
        $value = $record[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(sprintf('Missing non-empty %s.', $key));
        }

        return $value;
    }
}
