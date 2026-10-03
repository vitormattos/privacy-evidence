<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Rubix;

final readonly class RubixSignalPrediction
{
    public function __construct(
        public string $signal,
        public float $probability,
        public bool $candidatePresent,
        public float $threshold,
        public RubixSignalModelMetadata $model,
        public ?string $artifactSha256,
    ) {
    }

    /**
     * @return array{
     *   signal:string,
     *   probability:float,
     *   candidatePresent:bool,
     *   threshold:float,
     *   artifactSha256:?string,
     *   model:array<string,string>
     * }
     */
    public function toArray(): array
    {
        /** @var array<string,string> $metadata */
        $metadata = $this->model->toArray();

        return [
            'signal' => $this->signal,
            'probability' => $this->probability,
            'candidatePresent' => $this->candidatePresent,
            'threshold' => $this->threshold,
            'artifactSha256' => $this->artifactSha256,
            'model' => $metadata,
        ];
    }
}
