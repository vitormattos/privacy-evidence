<?php

declare(strict_types=1);

namespace PrivacyEvidence\Pipeline;

use PrivacyEvidence\Evidence\Detector\CookieInterfaceDetector;
use PrivacyEvidence\Evidence\Detector\PrivacyContactDetector;
use PrivacyEvidence\Evidence\Detector\PrivacyLawReferenceDetector;
use PrivacyEvidence\Evidence\Detector\PrivacyNoticeDetector;
use PrivacyEvidence\Evidence\Detector\RightsDetector;
use PrivacyEvidence\Evidence\Detector\TransparencyDetector;

final class DefaultDetectorRegistry
{
    public static function create(): DetectorRegistry
    {
        return new DetectorRegistry([
            new PrivacyNoticeDetector(),
            new PrivacyLawReferenceDetector(),
            new PrivacyContactDetector(),
            new RightsDetector(),
            new CookieInterfaceDetector(),
            new TransparencyDetector(),
        ]);
    }
}
