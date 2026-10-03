<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Rubix;

use PrivacyEvidence\Evidence\EvidenceType;
use Rubix\ML\Classifiers\GaussianNB;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Persistable;
use Rubix\ML\Persisters\Filesystem;
use Rubix\ML\Serializers\RBX;

use const Rubix\ML\VERSION as RUBIX_VERSION;

final class RubixSignalClassifier implements Persistable
{
    public const VERSION = '1.0.0';
    private const POSITIVE_LABEL = 'present';
    private const NEGATIVE_LABEL = 'absent';

    private ?string $artifactSha256 = null;

    private function __construct(
        private readonly EvidenceType $signal,
        private readonly RubixTextFeaturePipeline $pipeline,
        private readonly GaussianNB $estimator,
        private readonly RubixSignalModelMetadata $metadata,
    ) {
    }

    public function revision(): string
    {
        return self::VERSION;
    }

    /**
     * @param list<array{text:string,present:bool}> $examples
     */
    public static function train(
        EvidenceType $signal,
        array $examples,
        RubixSignalTrainingProvenance $provenance,
        int $maxVocabularySize = RubixTextFeaturePipeline::DEFAULT_MAX_VOCABULARY_SIZE,
    ): self {
        if ($examples === []) {
            throw new \InvalidArgumentException('Signal classifier requires training examples.');
        }

        $texts = [];
        $labels = [];
        $positive = $negative = 0;

        foreach ($examples as $example) {
            $text = trim($example['text']);
            if ($text === '') {
                throw new \InvalidArgumentException('Signal classifier training text cannot be empty.');
            }

            $texts[] = $text;
            if ($example['present']) {
                $labels[] = self::POSITIVE_LABEL;
                ++$positive;
            } else {
                $labels[] = self::NEGATIVE_LABEL;
                ++$negative;
            }
        }

        if ($positive === 0 || $negative === 0) {
            throw new \InvalidArgumentException('Signal classifier requires positive and negative examples.');
        }

        $pipeline = new RubixTextFeaturePipeline($maxVocabularySize);
        $features = $pipeline->fitTransform($texts);

        $estimator = new GaussianNB();
        $estimator->train(Labeled::build($features, $labels));

        $metadata = new RubixSignalModelMetadata(
            $signal->value,
            $provenance->datasetId,
            $provenance->datasetVersion,
            $provenance->datasetSha256,
            $provenance->splitSha256,
            $provenance->mappingVersion,
            RubixTextFeaturePipeline::VERSION,
            GaussianNB::class,
            RUBIX_VERSION,
            $provenance->trainedAt,
        );

        return new self($signal, $pipeline, $estimator, $metadata);
    }

    public function metadata(): RubixSignalModelMetadata
    {
        return $this->metadata;
    }

    public function predict(string $text, float $threshold = 0.5): RubixSignalPrediction
    {
        if ($threshold < 0.0 || $threshold > 1.0) {
            throw new \InvalidArgumentException('Prediction threshold must be between 0 and 1.');
        }

        $features = $this->pipeline->transform([$text]);
        $distribution = $this->estimator->proba(Unlabeled::build($features))[0] ?? null;
        if (!is_array($distribution)) {
            throw new \RuntimeException('Rubix classifier returned no probability distribution.');
        }

        $probability = $distribution[self::POSITIVE_LABEL] ?? null;
        if (!is_float($probability)) {
            throw new \RuntimeException('Rubix classifier returned no positive-class probability.');
        }

        return new RubixSignalPrediction(
            $this->signal->value,
            $probability,
            $probability >= $threshold,
            $threshold,
            $this->metadata,
            $this->artifactSha256,
        );
    }

    public function save(string $path): string
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create model artifact directory.');
        }

        $serializer = new RBX();
        /** @psalm-suppress InternalMethod Rubix documents RBX as its public persistence format. */
        $serializer->serialize($this)->saveTo(new Filesystem($path));

        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new \RuntimeException('Unable to hash model artifact.');
        }

        $sidecar = [
            'artifactSha256' => $hash,
            'model' => $this->metadata->toArray(),
        ];
        $encoded = json_encode(
            $sidecar,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;

        if (file_put_contents($path . '.metadata.json', $encoded, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write model artifact metadata.');
        }

        $this->artifactSha256 = $hash;

        return $hash;
    }

    public static function load(string $path): self
    {
        $metadataPath = $path . '.metadata.json';
        $encodedMetadata = @file_get_contents($metadataPath);
        if (!is_string($encodedMetadata)) {
            throw new \RuntimeException('Model artifact metadata sidecar is missing.');
        }

        $decoded = json_decode($encodedMetadata, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Model artifact metadata sidecar is invalid.');
        }

        $expectedHash = $decoded['artifactSha256'] ?? null;
        if (!is_string($expectedHash) || preg_match('/^[a-f0-9]{64}$/D', $expectedHash) !== 1) {
            throw new \RuntimeException('Model artifact metadata has an invalid SHA-256.');
        }

        $actualHash = hash_file('sha256', $path);
        if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
            throw new \RuntimeException('Model artifact checksum mismatch.');
        }

        $persistable = (new Filesystem($path))
            ->load()
            ->deserializeWith(new RBX());

        if (!$persistable instanceof self) {
            throw new \RuntimeException('Model artifact contains an incompatible object.');
        }

        $sidecarModel = $decoded['model'] ?? null;
        if (!is_array($sidecarModel) || $sidecarModel !== $persistable->metadata->toArray()) {
            throw new \RuntimeException('Model artifact metadata does not match embedded provenance.');
        }

        $persistable->artifactSha256 = $actualHash;

        return $persistable;
    }
}
