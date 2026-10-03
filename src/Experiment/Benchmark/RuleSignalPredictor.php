<?php

declare(strict_types=1);

namespace PrivacyEvidence\Experiment\Benchmark;

use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\Detector;
use PrivacyEvidence\Evidence\Detector\CookieInterfaceDetector;
use PrivacyEvidence\Evidence\Detector\PrivacyContactDetector;
use PrivacyEvidence\Evidence\Detector\RightsDetector;
use PrivacyEvidence\Evidence\Detector\TransparencyDetector;
use PrivacyEvidence\Evidence\EvidenceType;

final class RuleSignalPredictor
{
    public function supports(EvidenceType $signal): bool
    {
        return $this->detector($signal) instanceof Detector;
    }

    /**
     * @return array{present:bool,detector:string,version:string}
     */
    public function predict(EvidenceType $signal, string $text, string $id): array
    {
        $detector = $this->detector($signal);
        if (!$detector instanceof Detector) {
            throw new \InvalidArgumentException('No deterministic text detector supports signal ' . $signal->value . '.');
        }

        $document = new FetchedDocument(
            resourceId: 'benchmark:' . $id,
            requestedUrl: 'https://benchmark.invalid/' . rawurlencode($id),
            finalUrl: 'https://benchmark.invalid/' . rawurlencode($id),
            statusCode: 200,
            mediaType: 'text/plain',
            body: $text,
            fetchedAt: '1970-01-01T00:00:00Z',
        );

        foreach ($detector->detect($document) as $evidence) {
            if ($evidence->type === $signal) {
                return [
                    'present' => $evidence->state === ObservationState::Present,
                    'detector' => $detector->name(),
                    'version' => $detector->version(),
                ];
            }
        }

        throw new \RuntimeException('Deterministic detector did not emit requested signal.');
    }

    private function detector(EvidenceType $signal): ?Detector
    {
        return match ($signal) {
            EvidenceType::ControllerIdentity,
            EvidenceType::DpoIdentity,
            EvidenceType::DpoContact => new PrivacyContactDetector(),
            EvidenceType::PurposeDisclosure,
            EvidenceType::RecipientDisclosure,
            EvidenceType::RetentionDisclosure => new TransparencyDetector(),
            EvidenceType::RightsDisclosure => new RightsDetector(),
            EvidenceType::CookieNotice => new CookieInterfaceDetector(),
            default => null,
        };
    }
}
