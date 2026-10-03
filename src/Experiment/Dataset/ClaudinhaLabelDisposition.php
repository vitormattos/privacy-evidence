<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Dataset;

enum ClaudinhaLabelDisposition: string
{
    case Mappable = 'mappable';
    case PartiallyMappable = 'partially_mappable';
    case Excluded = 'excluded';
}
