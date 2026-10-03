<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Rubix;

final readonly class RubixSignalTrainingProvenance
{
    public function __construct(
        public string $datasetId,
        public string $datasetVersion,
        public string $datasetSha256,
        public string $splitSha256,
        public string $mappingVersion,
        public string $trainedAt,
    ) {
        foreach ([
            'datasetId' => $this->datasetId,
            'datasetVersion' => $this->datasetVersion,
            'datasetSha256' => $this->datasetSha256,
            'splitSha256' => $this->splitSha256,
            'mappingVersion' => $this->mappingVersion,
            'trainedAt' => $this->trainedAt,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException($field . ' cannot be empty.');
            }
        }

        foreach (['datasetSha256' => $this->datasetSha256, 'splitSha256' => $this->splitSha256] as $field => $hash) {
            if (preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
                throw new \InvalidArgumentException($field . ' must be a lowercase SHA-256 digest.');
            }
        }

        if (filter_var($this->trainedAt, FILTER_VALIDATE_REGEXP, [
            'options' => ['regexp' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D'],
        ]) === false) {
            throw new \InvalidArgumentException('trainedAt must be an explicit UTC timestamp.');
        }
    }
}
