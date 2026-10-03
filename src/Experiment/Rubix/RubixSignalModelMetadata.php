<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Rubix;

final readonly class RubixSignalModelMetadata
{
    public const ARTIFACT_VERSION = '1.0.0';

    public function __construct(
        public string $signal,
        public string $datasetId,
        public string $datasetVersion,
        public string $datasetSha256,
        public string $splitSha256,
        public string $mappingVersion,
        public string $pipelineVersion,
        public string $estimator,
        public string $rubixVersion,
        public string $trainedAt,
    ) {
    }

    /**
     * @return array{
     *   artifactVersion:string,
     *   signal:string,
     *   datasetId:string,
     *   datasetVersion:string,
     *   datasetSha256:string,
     *   splitSha256:string,
     *   mappingVersion:string,
     *   pipelineVersion:string,
     *   estimator:string,
     *   rubixVersion:string,
     *   trainedAt:string
     * }
     */
    public function toArray(): array
    {
        return [
            'artifactVersion' => self::ARTIFACT_VERSION,
            'signal' => $this->signal,
            'datasetId' => $this->datasetId,
            'datasetVersion' => $this->datasetVersion,
            'datasetSha256' => $this->datasetSha256,
            'splitSha256' => $this->splitSha256,
            'mappingVersion' => $this->mappingVersion,
            'pipelineVersion' => $this->pipelineVersion,
            'estimator' => $this->estimator,
            'rubixVersion' => $this->rubixVersion,
            'trainedAt' => $this->trainedAt,
        ];
    }
}
