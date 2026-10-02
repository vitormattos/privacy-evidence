<?php

declare(strict_types=1);

namespace PrivacyEvidence\Queue;

enum JobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Dead = 'dead';
}
