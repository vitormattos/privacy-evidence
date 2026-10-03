<?php

declare(strict_types=1);

namespace PrivacyEvidence\Command;

use PDO;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Evidence\Detector\PrivacyContactDetector;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use PrivacyEvidence\Experiment\Review\MlReviewIntegrator;
use PrivacyEvidence\Experiment\Rubix\RubixSignalClassifier;
use PrivacyEvidence\Review\SqliteReviewQueue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class HacktoberfestDemoCommand extends Command
{
    private const RUN_ID = 'hacktoberfest-demo-v1';
    private const REVIEWED_AT = '2026-10-03T12:05:00Z';
    private const TARGET_TEXT = 'A Empresa Exemplo é responsável pelo tratamento de suas informações pessoais.';

    public function __construct()
    {
        parent::__construct('experiment:hacktoberfest:demo');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run the reproducible local Hacktoberfest ML disagreement/review demo.')
            ->addArgument('artifact', InputArgument::REQUIRED, 'Previously built RBX model artifact.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $artifact = $input->getArgument('artifact');
        if (!is_string($artifact) || trim($artifact) === '') {
            return Command::INVALID;
        }

        try {
            $model = RubixSignalClassifier::load($artifact);
            $evidence = $this->ruleEvidence();
            $prediction = $model->predict(self::TARGET_TEXT);

            $queue = new SqliteReviewQueue(new PDO('sqlite::memory:'));
            $assessment = (new MlReviewIntegrator($queue))->record(
                self::RUN_ID,
                $evidence,
                $prediction,
                self::REVIEWED_AT,
            );
            $decisions = $queue->decisions(self::RUN_ID);

            $output->writeln(json_encode(
                [
                    'demoVersion' => '1.0.0',
                    'status' => 'experimental',
                    'aiAtCore' => true,
                    'modelRequired' => true,
                    'legalConclusion' => false,
                    'input' => [
                        'text' => self::TARGET_TEXT,
                        'signal' => EvidenceType::ControllerIdentity->value,
                    ],
                    'deterministicRule' => [
                        'state' => $evidence->state->value,
                        'detector' => $evidence->detector,
                        'detectorVersion' => $evidence->detectorVersion,
                    ],
                    'mlPrediction' => $prediction->toArray(),
                    'review' => [
                        'disagreesWithRule' => $assessment->disagreesWithRule,
                        'lowConfidence' => $assessment->lowConfidence,
                        'needsReview' => $assessment->needsReview,
                        'priority' => $assessment->priority,
                        'pendingCases' => count($queue->pending(self::RUN_ID)),
                        'suggestions' => $decisions,
                    ],
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ));

            return Command::SUCCESS;
        } catch (\RuntimeException | \InvalidArgumentException | \JsonException $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }

    private function ruleEvidence(): PrivacyEvidence
    {
        $document = new FetchedDocument(
            resourceId: 'hacktoberfest-demo-resource',
            requestedUrl: 'https://demo.invalid/privacy',
            finalUrl: 'https://demo.invalid/privacy',
            statusCode: 200,
            mediaType: 'text/plain',
            body: self::TARGET_TEXT,
            fetchedAt: self::REVIEWED_AT,
        );

        foreach ((new PrivacyContactDetector())->detect($document) as $evidence) {
            if ($evidence->type === EvidenceType::ControllerIdentity) {
                return $evidence;
            }
        }

        throw new \RuntimeException('Controller identity rule evidence was not emitted.');
    }
}
