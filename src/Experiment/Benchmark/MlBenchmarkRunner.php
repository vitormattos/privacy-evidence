<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Benchmark;

use PrivacyEvidence\Analysis\ConfusionMatrix;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelMapping;
use PrivacyEvidence\Experiment\Rubix\ExternalSignalTrainingSet;
use PrivacyEvidence\Experiment\Rubix\RubixSignalClassifier;
use PrivacyEvidence\Experiment\Text\MultinomialNaiveBayes;

final class MlBenchmarkRunner
{
    /** @var list<EvidenceType> */
    private const SIGNALS = [
        EvidenceType::ControllerIdentity,
        EvidenceType::PurposeDisclosure,
        EvidenceType::RecipientDisclosure,
        EvidenceType::RetentionDisclosure,
        EvidenceType::DpoIdentity,
        EvidenceType::DpoContact,
        EvidenceType::RightsDisclosure,
        EvidenceType::CookieNotice,
    ];

    public function __construct(
        private readonly ExternalSignalTrainingSet $trainingSet = new ExternalSignalTrainingSet(),
        private readonly ExternalSignalEvaluationSet $evaluationSet = new ExternalSignalEvaluationSet(),
        private readonly RuleSignalPredictor $rulePredictor = new RuleSignalPredictor(),
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function run(
        string $trainingPath,
        string $evaluationPath,
        string $manifestPath,
        string $trainedAt,
    ): array {
        $manifest = $this->readManifest($manifestPath);
        $trainingSha256 = hash_file('sha256', $trainingPath);
        $evaluationSha256 = hash_file('sha256', $evaluationPath);
        if (!is_string($trainingSha256) || !is_string($evaluationSha256)) {
            throw new \InvalidArgumentException('Unable to hash benchmark partitions.');
        }

        $signals = [];
        foreach (self::SIGNALS as $signal) {
            $signals[$signal->value] = $this->benchmarkSignal(
                $signal,
                $trainingPath,
                $evaluationPath,
                $manifestPath,
                $trainedAt,
            );
        }

        return [
            'schemaVersion' => '1.0.0',
            'scope' => 'external-development-only',
            'adoptionGateSatisfied' => false,
            'automaticPromotion' => false,
            'dataset' => [
                'id' => $this->stringField($manifest, 'datasetId'),
                'version' => $this->stringField($manifest, 'datasetVersion'),
                'canonicalSha256' => $this->stringField($manifest, 'canonicalSha256'),
                'trainingPartitionSha256' => $trainingSha256,
                'evaluationPartitionSha256' => $evaluationSha256,
                'mappingVersion' => ClaudinhaLabelMapping::VERSION,
            ],
            'runtime' => [
                'php' => PHP_VERSION,
            ],
            'signals' => $signals,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function benchmarkSignal(
        EvidenceType $signal,
        string $trainingPath,
        string $evaluationPath,
        string $manifestPath,
        string $trainedAt,
    ): array {
        $loaded = $this->trainingSet->load($trainingPath, $manifestPath, $signal, $trainedAt);
        $cases = $this->evaluationSet->load($evaluationPath, $signal);
        if ($cases === []) {
            throw new \InvalidArgumentException('Evaluation partition is empty.');
        }

        $positive = count(array_filter($cases, static fn (array $case): bool => $case['present']));
        $negative = count($cases) - $positive;

        $rulePredictions = [];
        $ruleIdentity = null;
        foreach ($cases as $case) {
            $prediction = $this->rulePredictor->predict($signal, $case['text'], $case['id']);
            $rulePredictions[$case['id']] = $prediction['present'];
            $ruleIdentity ??= $prediction['detector'] . '@' . $prediction['version'];
        }

        $nb = new MultinomialNaiveBayes();
        $nbDocuments = array_map(
            static fn (array $example): array => [
                'label' => $example['present'] ? 'present' : 'absent',
                'text' => $example['text'],
            ],
            $loaded['examples'],
        );
        $nb->train($nbDocuments);

        $nbPredictions = [];
        foreach ($cases as $case) {
            $nbPredictions[$case['id']] = $nb->classify($case['text'])->label === 'present';
        }

        $rubix = RubixSignalClassifier::train(
            $signal,
            $loaded['examples'],
            $loaded['provenance'],
        );

        $tempRoot = sys_get_temp_dir() . '/privacy-evidence-benchmark-' . bin2hex(random_bytes(6));
        mkdir($tempRoot, 0700, true);
        $artifact = $tempRoot . '/' . $signal->value . '.rbx';

        try {
            $rubixHash = $rubix->save($artifact);
            $rubixPredictions = [];
            foreach ($cases as $case) {
                $rubixPredictions[$case['id']] = $rubix->predict($case['text'])->candidatePresent;
            }
        } finally {
            foreach ([$artifact, $artifact . '.metadata.json'] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($tempRoot)) {
                rmdir($tempRoot);
            }
        }

        $nbIdentity = hash(
            'sha256',
            json_encode(
                [
                    'classifier' => MultinomialNaiveBayes::CLASSIFIER_ID,
                    'version' => MultinomialNaiveBayes::CLASSIFIER_VERSION,
                    'signal' => $signal->value,
                    'training' => $nbDocuments,
                ],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ),
        );

        return [
            'classDistribution' => [
                'positive' => $positive,
                'negative' => $negative,
            ],
            'rule' => $this->evaluate(
                $cases,
                $rulePredictions,
                $ruleIdentity,
                hash('sha256', $ruleIdentity),
            ),
            'internalNaiveBayes' => $this->evaluate(
                $cases,
                $nbPredictions,
                MultinomialNaiveBayes::CLASSIFIER_ID . '@' . MultinomialNaiveBayes::CLASSIFIER_VERSION,
                $nbIdentity,
            ),
            'rubix' => $this->evaluate(
                $cases,
                $rubixPredictions,
                $rubix->metadata()->estimator . '@' . $rubix->metadata()->rubixVersion,
                $rubixHash,
            ),
        ];
    }

    /**
     * @param list<array{id:string,text:string,present:bool,language:string,sourceRecordIds:list<string>}> $cases
     * @param array<string,bool> $predictions
     * @return array<string,mixed>
     */
    private function evaluate(array $cases, array $predictions, string $model, string $modelSha256): array
    {
        $tp = $fp = $tn = $fn = 0;
        $errors = [];

        foreach ($cases as $case) {
            $predicted = $predictions[$case['id']] ?? null;
            if (!is_bool($predicted)) {
                throw new \RuntimeException('Benchmark candidate did not predict every evaluation case.');
            }

            if ($predicted && $case['present']) {
                ++$tp;
            } elseif ($predicted) {
                ++$fp;
            } elseif ($case['present']) {
                ++$fn;
            } else {
                ++$tn;
            }

            if ($predicted !== $case['present']) {
                $errors[] = [
                    'paragraphId' => $case['id'],
                    'sourceRecordIds' => $case['sourceRecordIds'],
                    'language' => $case['language'],
                    'predicted' => $predicted ? 'present' : 'absent',
                    'actual' => $case['present'] ? 'present' : 'absent',
                ];
            }
        }

        $matrix = new ConfusionMatrix($tp, $fp, $tn, $fn);

        return [
            'model' => $model,
            'modelSha256' => $modelSha256,
            'confusionMatrix' => [
                'truePositive' => $tp,
                'falsePositive' => $fp,
                'trueNegative' => $tn,
                'falseNegative' => $fn,
            ],
            'precision' => $matrix->precision(),
            'recall' => $matrix->recall(),
            'f1' => $matrix->f1(),
            'support' => $matrix->support(),
            'coverage' => 1.0,
            'abstained' => 0,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function readManifest(string $path): array
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            throw new \InvalidArgumentException('Dataset manifest does not exist.');
        }

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Dataset manifest must decode to an object.');
        }

        $result = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $source
     */
    private function stringField(array $source, string $field): string
    {
        $value = $source[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('Missing manifest field: ' . $field);
        }

        return $value;
    }
}
