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
            ->setDescription('Import completed human annotations into the immutable review history.')
            ->addArgument('package', InputArgument::REQUIRED)
            ->addArgument('reviewer-id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('package');
        $reviewerId = $input->getArgument('reviewer-id');

        if (!is_string($path) || $path === '' || !is_file($path)) {
            return Command::INVALID;
        }
        if (!is_string($reviewerId) || $reviewerId === '' || str_starts_with($reviewerId, 'ai:')) {
            return Command::INVALID;
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            return Command::INVALID;
        }

        if (($decoded['testMode'] ?? false) !== false) {
            $output->writeln('<error>Form test packages cannot be imported as human annotations.</error>');

            return Command::INVALID;
        }

        $runId = $decoded['runId'] ?? null;
        $cases = $decoded['cases'] ?? null;
        if (!is_string($runId) || $runId === '' || !is_array($cases)) {
            return Command::INVALID;
        }

        $runtime = RuntimeFactory::create($this->projectRoot);
        if ($runtime->runs->get($runId) === null) {
            $output->writeln('<error>Unknown run.</error>');

            return Command::FAILURE;
        }

        $evidenceById = [];
        foreach ($runtime->observations->evidence($runId) as $evidence) {
            $evidenceById[$evidence->id()] = $evidence;
        }

        $imported = 0;
        $deferred = [];
        foreach ($cases as $case) {
            if (!is_array($case)) {
                return Command::INVALID;
            }

            $evidenceId = $case['evidenceId'] ?? null;
            $typeRaw = $case['evidenceType'] ?? null;
            $stateRaw = $case['humanState'] ?? null;
            $rationale = $case['rationale'] ?? null;

            $sourceEvidence = is_string($evidenceId) ? ($evidenceById[$evidenceId] ?? null) : null;
            if (
                is_string($evidenceId)
                && $sourceEvidence !== null
                && $sourceEvidence->type->value === $typeRaw
                && ($sourceEvidence->excerpt === null || trim($sourceEvidence->excerpt) === '')
                && $stateRaw === null
                && $rationale === null
                && ($case['reviewedAt'] ?? null) === null
            ) {
                $deferred[] = $evidenceId;

                continue;
            }

            if (
                !is_string($evidenceId)
                || !is_string($typeRaw)
                || !is_string($stateRaw)
                || !is_string($rationale)
                || trim($rationale) === ''
            ) {
                return Command::INVALID;
            }

            $sourceEvidence = $evidenceById[$evidenceId] ?? null;
            $type = EvidenceType::tryFrom($typeRaw);
            $state = ObservationState::tryFrom($stateRaw);
            if ($sourceEvidence === null || $type === null || $state === null || $sourceEvidence->type !== $type) {
                return Command::INVALID;
            }

            $runtime->reviews->decide(new ReviewDecision(
                runId: $runId,
                evidenceId: $evidenceId,
                type: $type,
                state: $state,
                reviewerType: ReviewerType::Human,
                reviewerId: $reviewerId,
                reviewedAt: isset($case['reviewedAt'])
                    && is_string($case['reviewedAt'])
                    && $case['reviewedAt'] !== ''
                        ? $case['reviewedAt']
                        : gmdate(DATE_ATOM),
                rationale: $rationale,
            ));
            $imported++;
        }

        $output->writeln(json_encode([
            'runId' => $runId,
            'reviewerId' => $reviewerId,
            'imported' => $imported,
            'deferred' => count($deferred),
            'deferredEvidenceIds' => $deferred,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
