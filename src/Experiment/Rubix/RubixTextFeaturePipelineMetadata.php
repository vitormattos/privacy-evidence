<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Rubix;

final readonly class RubixTextFeaturePipelineMetadata
{
    public function __construct(
        public string $pipelineVersion,
        public string $rubixVersion,
        public int $maxVocabularySize,
        public int $dimensions,
        public string $normalizer,
        public string $vectorizer,
        public string $weighting,
    ) {
    }

    /**
     * @return array{
     *   pipelineVersion:string,
     *   rubixVersion:string,
     *   maxVocabularySize:int,
     *   dimensions:int,
     *   normalizer:string,
     *   vectorizer:string,
     *   weighting:string
     * }
     */
    public function toArray(): array
    {
        return [
            'pipelineVersion' => $this->pipelineVersion,
            'rubixVersion' => $this->rubixVersion,
            'maxVocabularySize' => $this->maxVocabularySize,
            'dimensions' => $this->dimensions,
            'normalizer' => $this->normalizer,
            'vectorizer' => $this->vectorizer,
            'weighting' => $this->weighting,
        ];
    }
}
