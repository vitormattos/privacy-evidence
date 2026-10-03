<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Text;

/**
 * Small deterministic multinomial Naive Bayes baseline for research experiments.
 *
 * This class is intentionally dependency-free and is not a production detector.
 */
final class MultinomialNaiveBayes implements TextClassifier
{
    public const CLASSIFIER_ID = 'dependency-free-multinomial-naive-bayes';
    public const CLASSIFIER_VERSION = '1.0.0';
    /** @var array<string,array<string,int>> */
    private array $tokenCounts = [];

    /** @var array<string,int> */
    private array $tokenTotals = [];

    /** @var array<string,int> */
    private array $documentCounts = [];

    /** @var array<string,bool> */
    private array $vocabulary = [];

    private int $documents = 0;

    /**
     * @param list<array{label:string,text:string}> $documents
     */
    public function train(array $documents): void
    {
        $this->tokenCounts = [];
        $this->tokenTotals = [];
        $this->documentCounts = [];
        $this->vocabulary = [];
        $this->documents = 0;

        foreach ($documents as $document) {
            $label = trim($document['label']);
            if ($label === '') {
                throw new \InvalidArgumentException('Training label cannot be empty.');
            }

            $this->documentCounts[$label] = ($this->documentCounts[$label] ?? 0) + 1;
            $this->documents++;

            foreach ($this->tokens($document['text']) as $token) {
                $this->vocabulary[$token] = true;
                $this->tokenCounts[$label][$token] = ($this->tokenCounts[$label][$token] ?? 0) + 1;
                $this->tokenTotals[$label] = ($this->tokenTotals[$label] ?? 0) + 1;
            }
        }

        if (count($this->documentCounts) < 2) {
            throw new \InvalidArgumentException('At least two labels are required.');
        }
    }

    public function classify(string $text): TextClassificationResult
    {
        if ($this->documents === 0) {
            throw new \LogicException('Classifier must be trained before prediction.');
        }

        $vocabularySize = max(1, count($this->vocabulary));
        $scores = [];

        foreach ($this->documentCounts as $label => $documentCount) {
            $score = log((float) $documentCount / (float) $this->documents);
            $denominator = ($this->tokenTotals[$label] ?? 0) + $vocabularySize;

            foreach ($this->tokens($text) as $token) {
                $count = $this->tokenCounts[$label][$token] ?? 0;
                $score += log((float) ($count + 1) / (float) $denominator);
            }

            $scores[$label] = $score;
        }

        arsort($scores);
        $label = array_key_first($scores);
        if (!is_string($label)) {
            throw new \LogicException('Classifier produced no label.');
        }

        return new TextClassificationResult(
            $label,
            $scores,
            self::CLASSIFIER_ID,
            self::CLASSIFIER_VERSION,
        );
    }

    /**
     * @return array{label:string,scores:array<string,float>}
     */
    public function predict(string $text): array
    {
        $result = $this->classify($text);

        return ['label' => $result->label, 'scores' => $result->scores];
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $normalized = mb_strtolower(strip_tags($text));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return [];
        }

        return array_values(array_filter(
            $parts,
            static fn (string $token): bool => mb_strlen($token) >= 2,
        ));
    }
}
