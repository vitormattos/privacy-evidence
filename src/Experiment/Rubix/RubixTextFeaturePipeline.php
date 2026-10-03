<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Rubix;

use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Persistable;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Serializers\RBX;
use Rubix\ML\Transformers\MultibyteTextNormalizer;
use Rubix\ML\Transformers\TfIdfTransformer;
use Rubix\ML\Transformers\WordCountVectorizer;

use const Rubix\ML\VERSION as RUBIX_VERSION;

final class RubixTextFeaturePipeline implements Persistable
{
    public const VERSION = '1.0.0';
    public const DEFAULT_MAX_VOCABULARY_SIZE = 2048;
    public const MAX_VOCABULARY_SIZE = 8192;
    public const MAX_TEXT_BYTES = 65536;

    private MultibyteTextNormalizer $normalizer;
    private WordCountVectorizer $vectorizer;
    private TfIdfTransformer $weighting;
    private int $dimensions = 0;

    public function __construct(
        private readonly int $maxVocabularySize = self::DEFAULT_MAX_VOCABULARY_SIZE,
    ) {
        if ($this->maxVocabularySize < 1 || $this->maxVocabularySize > self::MAX_VOCABULARY_SIZE) {
            throw new \InvalidArgumentException(
                'Maximum vocabulary size must be between 1 and ' . self::MAX_VOCABULARY_SIZE . '.',
            );
        }

        $this->normalizer = new MultibyteTextNormalizer();
        $this->vectorizer = new WordCountVectorizer(
            maxVocabularySize: $this->maxVocabularySize,
            minDocumentCount: 1,
            maxDocumentRatio: 1.0,
        );
        $this->weighting = new TfIdfTransformer();
    }

    public function revision(): string
    {
        return self::VERSION;
    }

    /**
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public function fitTransform(array $texts): array
    {
        if ($texts === []) {
            throw new \InvalidArgumentException('Feature pipeline requires at least one training text.');
        }

        foreach ($texts as $text) {
            $this->validateText($text, false);
        }

        foreach ($texts as $text) {
            $this->validateText($text, true);
        }

        $dataset = $this->dataset($texts);
        $dataset
            ->apply($this->normalizer)
            ->apply($this->vectorizer);

        $dataset = Unlabeled::quick($this->numericSamples($dataset->samples()));
        $dataset->apply($this->weighting);

        $this->dimensions = $dataset->numFeatures();

        return $this->numericSamples($dataset->samples());
    }

    /**
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public function transform(array $texts): array
    {
        if (!$this->fitted()) {
            throw new \LogicException('Feature pipeline must be fitted before inference.');
        }

        if ($texts === []) {
            return [];
        }

        foreach ($texts as $text) {
            $this->validateText($text, true);
        }

        $dataset = $this->dataset($texts);
        $dataset
            ->apply($this->normalizer)
            ->apply($this->vectorizer);

        $dataset = Unlabeled::quick($this->numericSamples($dataset->samples()));
        $dataset->apply($this->weighting);

        if ($dataset->numFeatures() !== $this->dimensions) {
            throw new \RuntimeException('Feature dimension changed after fitted-pipeline inference.');
        }

        return $this->numericSamples($dataset->samples());
    }

    public function fitted(): bool
    {
        return $this->dimensions > 0
            && $this->vectorizer->fitted()
            && $this->weighting->fitted();
    }

    public function metadata(): RubixTextFeaturePipelineMetadata
    {
        return new RubixTextFeaturePipelineMetadata(
            self::VERSION,
            RUBIX_VERSION,
            $this->maxVocabularySize,
            $this->dimensions,
            MultibyteTextNormalizer::class,
            WordCountVectorizer::class,
            TfIdfTransformer::class,
        );
    }

    public function save(string $path): void
    {
        if (!$this->fitted()) {
            throw new \LogicException('Cannot persist an unfitted feature pipeline.');
        }

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create feature-pipeline artifact directory.');
        }

        $serializer = new RBX();
        /** @psalm-suppress InternalMethod Rubix documents RBX as the public persistence format. */
        $serializer->serialize($this)->saveTo(new Filesystem($path));
    }

    public static function load(string $path): self
    {
        $persistable = (new Filesystem($path))
            ->load()
            ->deserializeWith(new RBX());

        if (!$persistable instanceof self) {
            throw new \RuntimeException('Feature-pipeline artifact contains an incompatible object.');
        }

        if (!$persistable->fitted()) {
            throw new \RuntimeException('Feature-pipeline artifact is not fitted.');
        }

        return $persistable;
    }

    private function validateText(string $text, bool $allowEmpty): void
    {
        if (!$allowEmpty && trim($text) === '') {
            throw new \InvalidArgumentException('Training text cannot be empty.');
        }

        if (strlen($text) > self::MAX_TEXT_BYTES) {
            throw new \InvalidArgumentException(
                'Text exceeds the maximum of ' . self::MAX_TEXT_BYTES . ' bytes.',
            );
        }
    }

    /**
     * @param list<string> $texts
     */
    private function dataset(array $texts): Unlabeled
    {
        $samples = [];
        foreach ($texts as $text) {
            $samples[] = [$text];
        }

        return Unlabeled::build($samples);
    }

    /**
     * @param list<list<mixed>> $samples
     * @return list<list<float>>
     */
    private function numericSamples(array $samples): array
    {
        $result = [];

        foreach ($samples as $sample) {
            $row = [];
            /** @psalm-suppress MixedAssignment Rubix dataset samples are intentionally mixed at the boundary. */
            foreach ($sample as $value) {
                if (!is_int($value) && !is_float($value)) {
                    throw new \RuntimeException('Feature pipeline produced a non-numeric value.');
                }

                $row[] = (float) $value;
            }
            $result[] = $row;
        }

        return $result;
    }
}
