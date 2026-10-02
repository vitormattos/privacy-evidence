<?php

declare(strict_types=1);

namespace PrivacyEvidence\Runtime;

use PrivacyEvidence\Queue\JobQueue;
use PrivacyEvidence\Review\ReviewQueue;
use PrivacyEvidence\Run\RunStore;
use PrivacyEvidence\Storage\ObservationStore;

final readonly class RuntimeContext
{
    public function __construct(
        public RunStore $runs,
        public JobQueue $jobs,
        public ObservationStore $observations,
        public ReviewQueue $reviews,
        public string $artifactDirectory,
    ) {
    }
}
