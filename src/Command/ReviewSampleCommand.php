<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PrivacyEvidence\Review\GoldSampler;
use PrivacyEvidence\Review\ReviewMaterial;
use PrivacyEvidence\Runtime\RuntimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ReviewSampleCommand extends Command
{
    public function __construct(private readonly string $projectRoot)
    {
        parent::__construct('review:sample');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a deterministic, stratified human-annotation package.')
            ->addArgument('run-id', InputArgument::REQUIRED)
            ->addArgument('output', InputArgument::REQUIRED)
            ->addOption('per-stratum', null, InputOption::VALUE_REQUIRED, 'Cases per evidence/state/review stratum.', '2')
            ->addOption('seed', null, InputOption::VALUE_REQUIRED, 'Deterministic sampling seed.', 'privacy-evidence-gold-v1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runId = $input->getArgument('run-id');
        $path = $input->getArgument('output');
        $perStratumRaw = $input->getOption('per-stratum');
        $seed = $input->getOption('seed');

        if (!is_string($runId) || $runId === '' || !is_string($path) || $path === '') {
            return Command::INVALID;
        }
        if (!is_string($perStratumRaw) || !ctype_digit($perStratumRaw) || (int) $perStratumRaw <= 0) {
            return Command::INVALID;
        }
        if (!is_string($seed) || $seed === '') {
            return Command::INVALID;
        }

        $runtime = RuntimeFactory::create($this->projectRoot);
        if ($runtime->runs->get($runId) === null) {
            $output->writeln('<error>Unknown run.</error>');

            return Command::FAILURE;
        }

        $sample = (new GoldSampler())->sample(
            $runtime->observations->evidence($runId),
            (int) $perStratumRaw,
            $seed,
        );

        $cases = [];
        foreach ($sample as $evidence) {
            $cases[] = [
                'evidenceId' => $evidence->id(),
                'resourceId' => $evidence->resourceId,
                'evidenceType' => $evidence->type->value,
                'automatedState' => $evidence->state->value,
                'sourceUrl' => $evidence->sourceUrl,
                'artifactHash' => $evidence->artifactHash,
                'excerpt' => $evidence->excerpt,
                'detector' => $evidence->detector,
                'detectorVersion' => $evidence->detectorVersion,
                'confidence' => $evidence->confidence,
                'needsReview' => $evidence->needsReview,
                'humanState' => null,
                'rationale' => null,
                'reviewedAt' => null,
            ];
        }

        $package = [
            'packageVersion' => '1.0.0',
            'annotationHandbookVersion' => '1.0.0',
            'runId' => $runId,
            'seed' => $seed,
            'perStratum' => (int) $perStratumRaw,
            'cases' => $cases,
        ];

        $package = (new ReviewMaterial(
            $runtime->observations->resourceRecords($runId),
            $runtime->observations->documentRecords($runId),
            $runtime->artifactDirectory,
        ))->enrich($package);

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create annotation package directory.');
        }

        file_put_contents(
            $path,
            json_encode($package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );

        $output->writeln(json_encode([
            'path' => $path,
            'cases' => count($cases),
            'sha256' => hash_file('sha256', $path),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
