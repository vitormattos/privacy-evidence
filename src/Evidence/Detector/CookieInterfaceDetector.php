<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence\Detector;

use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\Detector;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;

final class CookieInterfaceDetector implements Detector
{
    /** @var list<string> */
    private const TRACKING_COOKIE_PREFIXES = [
        '_ga', '_gid', '_gat', '_fbp', '_gcl_', '_hj', 'IDE', 'NID',
    ];

    public function name(): string
    {
        return 'cookie_interface';
    }

    public function version(): string
    {
        return '1.1.0';
    }

    public function detect(FetchedDocument $document): array
    {
        $spacedHtml = preg_replace('/<[^>]+>/', ' ', $document->body) ?? $document->body;
        $text = mb_strtolower(
            html_entity_decode($spacedHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $evidence = [
            $this->signal(
                $document,
                EvidenceType::CookieNotice,
                preg_match('/\b(?:cookie|cookies)\b/u', $text) === 1,
                'cookie_term',
            ),
            $this->signal(
                $document,
                EvidenceType::CookieAcceptControl,
                preg_match('/\b(?:aceitar|accept(?: all)?)\b/u', $text) === 1,
                'accept_control_text',
            ),
            $this->signal(
                $document,
                EvidenceType::CookieRejectControl,
                preg_match('/\b(?:rejeitar|recusar|reject(?: all)?|decline)\b/u', $text) === 1,
                'reject_control_text',
            ),
            $this->signal(
                $document,
                EvidenceType::CookiePreferencesControl,
                preg_match('/(?:prefer[eê]ncias|preferences|configurar|settings)/u', $text) === 1,
                'preferences_control_text',
            ),
        ];

        $evidence[] = $this->nonEssentialStorageEvidence($document);
        $evidence[] = $this->thirdPartyRequestEvidence($document);

        return $evidence;
    }

    private function nonEssentialStorageEvidence(FetchedDocument $document): PrivacyEvidence
    {
        if ($document->acquisitionMode !== 'browser') {
            return $this->dynamicUnknown(
                $document,
                EvidenceType::NonEssentialStorageBeforeConsent,
                'browser_observation_required',
            );
        }

        $browser = $document->metadata['browser'] ?? null;
        $cookies = is_array($browser) ? ($browser['cookies'] ?? null) : null;
        if (!is_array($cookies)) {
            return $this->dynamicUnknown(
                $document,
                EvidenceType::NonEssentialStorageBeforeConsent,
                'cookies_unavailable',
            );
        }

        $knownTracking = [];
        $unknownCount = 0;
        foreach ($cookies as $cookie) {
            if (!is_array($cookie) || !is_string($cookie['name'] ?? null)) {
                continue;
            }

            $name = $cookie['name'];
            if ($this->isKnownTrackingCookie($name)) {
                $knownTracking[] = $name;
            } else {
                $unknownCount++;
            }
        }

        if ($knownTracking !== []) {
            return new PrivacyEvidence(
                type: EvidenceType::NonEssentialStorageBeforeConsent,
                state: ObservationState::Present,
                resourceId: $document->resourceId,
                artifactHash: $document->sha256,
                sourceUrl: $document->finalUrl,
                detector: $this->name(),
                detectorVersion: $this->version(),
                method: 'browser_cookie_before_interaction',
                confidence: 0.9,
                needsReview: false,
                attributes: [
                    'knownTrackingCookies' => count($knownTracking),
                    'sampleCookie' => $knownTracking[0],
                ],
            );
        }

        if ($unknownCount > 0) {
            return new PrivacyEvidence(
                type: EvidenceType::NonEssentialStorageBeforeConsent,
                state: ObservationState::Unknown,
                resourceId: $document->resourceId,
                artifactHash: $document->sha256,
                sourceUrl: $document->finalUrl,
                detector: $this->name(),
                detectorVersion: $this->version(),
                method: 'browser_cookie_before_interaction',
                confidence: 0.5,
                needsReview: true,
                attributes: ['unclassifiedCookies' => $unknownCount],
            );
        }

        return new PrivacyEvidence(
            type: EvidenceType::NonEssentialStorageBeforeConsent,
            state: ObservationState::Absent,
            resourceId: $document->resourceId,
            artifactHash: $document->sha256,
            sourceUrl: $document->finalUrl,
            detector: $this->name(),
            detectorVersion: $this->version(),
            method: 'browser_cookie_before_interaction',
            confidence: 0.95,
        );
    }

    private function thirdPartyRequestEvidence(FetchedDocument $document): PrivacyEvidence
    {
        if ($document->acquisitionMode !== 'browser') {
            return $this->dynamicUnknown(
                $document,
                EvidenceType::ThirdPartyRequestsBeforeConsent,
                'browser_observation_required',
            );
        }

        $browser = $document->metadata['browser'] ?? null;
        $requests = is_array($browser) ? ($browser['requests'] ?? null) : null;
        if (!is_array($requests)) {
            return $this->dynamicUnknown(
                $document,
                EvidenceType::ThirdPartyRequestsBeforeConsent,
                'requests_unavailable',
            );
        }

        $pageHost = parse_url($document->finalUrl, PHP_URL_HOST);
        if (!is_string($pageHost) || $pageHost === '') {
            return $this->dynamicUnknown(
                $document,
                EvidenceType::ThirdPartyRequestsBeforeConsent,
                'page_host_unavailable',
            );
        }

        $thirdParty = [];
        foreach ($requests as $request) {
            if (!is_array($request) || !is_string($request['url'] ?? null)) {
                continue;
            }
            $host = parse_url($request['url'], PHP_URL_HOST);
            if (is_string($host) && $host !== '' && strtolower($host) !== strtolower($pageHost)) {
                $thirdParty[] = $host;
            }
        }

        return new PrivacyEvidence(
            type: EvidenceType::ThirdPartyRequestsBeforeConsent,
            state: $thirdParty === [] ? ObservationState::Absent : ObservationState::Present,
            resourceId: $document->resourceId,
            artifactHash: $document->sha256,
            sourceUrl: $document->finalUrl,
            detector: $this->name(),
            detectorVersion: $this->version(),
            method: 'browser_requests_before_interaction',
            confidence: 0.9,
            needsReview: false,
            attributes: [
                'thirdPartyRequestCount' => count($thirdParty),
                'sampleThirdPartyHost' => $thirdParty[0] ?? null,
            ],
        );
    }

    private function isKnownTrackingCookie(string $name): bool
    {
        foreach (self::TRACKING_COOKIE_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function dynamicUnknown(
        FetchedDocument $document,
        EvidenceType $type,
        string $reason,
    ): PrivacyEvidence {
        return new PrivacyEvidence(
            type: $type,
            state: ObservationState::Unknown,
            resourceId: $document->resourceId,
            artifactHash: $document->sha256,
            sourceUrl: $document->finalUrl,
            detector: $this->name(),
            detectorVersion: $this->version(),
            method: $reason,
            confidence: 0.0,
            needsReview: false,
        );
    }

    private function signal(
        FetchedDocument $document,
        EvidenceType $type,
        bool $present,
        string $method,
    ): PrivacyEvidence {
        return new PrivacyEvidence(
            type: $type,
            state: $present ? ObservationState::Present : ObservationState::Absent,
            resourceId: $document->resourceId,
            artifactHash: $document->sha256,
            sourceUrl: $document->finalUrl,
            detector: $this->name(),
            detectorVersion: $this->version(),
            method: $method,
            confidence: $present ? 0.8 : 0.65,
            needsReview: !$present,
        );
    }
}
