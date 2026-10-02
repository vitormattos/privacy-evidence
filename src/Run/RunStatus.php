<?php

declare(strict_types=1);

namespace PrivacyEvidence\Run;

enum RunStatus: string
{
    case Created = 'created';
    case Running = 'running';
    case Interrupted = 'interrupted';
    case Completed = 'completed';
    case Failed = 'failed';
}
