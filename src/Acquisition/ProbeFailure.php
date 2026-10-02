<?php

declare(strict_types=1);

namespace PrivacyEvidence\Acquisition;

enum ProbeFailure: string
{
    case InvalidUrl = 'invalid_url';
    case PrivateNetwork = 'private_network';
    case Dns = 'dns_failure';
    case Tls = 'tls_failure';
    case Timeout = 'timeout';
    case Transport = 'transport_failure';
}
