<?php

declare(strict_types=1);

namespace PrivacyEvidence\Core;

enum ObservationState: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Unknown = 'unknown';
    case Unavailable = 'unavailable';
    case Invalid = 'invalid';
    case Excluded = 'excluded';
    case NotApplicable = 'not_applicable';
}
