<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Rubix;

use Rubix\ML\Classifiers\KNearestNeighbors;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Unlabeled;

final class RubixBackendProbe
{
    public const ENGINE_VERSION = '3.0.0-rc4';
    public const TENSOR_VERSION = '4.0.0-rc2';

    public function check(): RubixBackendStatus
    {
        $training = Labeled::build(
            [[0.0], [0.1], [0.9], [1.0]],
            ['near-zero', 'near-zero', 'near-one', 'near-one'],
        );

        $estimator = new KNearestNeighbors(1);
        $estimator->train($training);

        $prediction = $estimator->predict(Unlabeled::build([[0.05]]))[0] ?? null;
        if (!is_string($prediction)) {
            throw new \RuntimeException('Rubix smoke classifier returned no string prediction.');
        }

        return new RubixBackendStatus(
            $prediction,
            self::ENGINE_VERSION,
            self::TENSOR_VERSION,
        );
    }
}
