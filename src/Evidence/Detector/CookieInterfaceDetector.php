<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence\Detector;

use DOMElement;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Core\Value;
use PrivacyEvidence\Evidence\Detector;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;
use Symfony\Component\DomCrawler\Crawler;

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
        return '1.3.0';
    }

    public function detect(FetchedDocument $document): array
    {
        $spacedHtml = preg_replace('/<[^>]+>/', ' ', $document->body) ?? $document->body;
        $text = mb_strtolower(
            html_entity_decode($spacedHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        $interactiveLabels = $this->interactiveControlLabels($document->body);
        $hasCookieContext = preg_match('/\bcookies?\b/u', $text) === 1;

        $acceptControl = $hasCookieContext && (
            preg_match(
                '/\b(?:aceitar|aceitar todos|accept|accept all|allow all)\b/u',
                $interactiveLabels,
            ) === 1
            || preg_match(
                '/\b(?:aceitar(?: todos os)? cookies?|accept(?: all)? cookies?|allow all cookies?)\b/u',
                $text,
            ) === 1
        );
        $rejectControl = $hasCookieContext && (
            preg_match(
                '/\b(?:rejeitar|recusar|reject|reject all|decline)\b/u',
                $interactiveLabels,
            ) === 1
            || preg_match(
                '/\b(?:rejeitar cookies?|recusar cookies?|reject(?: all)? cookies?|decline cookies?)\b/u',
                $text,
            ) === 1
        );
        $preferencesControl = $hasCookieContext && (
            preg_match(
                '/\b(?:prefer[eê]ncias|configurar|configura[cç][oõ]es|gerenciar|preferences|settings|manage)\b/u',
                $interactiveLabels,
            ) === 1
            || preg_match(
                '/\b(?:prefer[eê]ncias de cookies?|configurar cookies?|configura[cç][oõ]es de cookies?|gerenciar cookies?|cookie preferences|cookie settings|manage cookies?)\b/u',
                $text,
            ) === 1
        );
        $cookieNotice = (
            $acceptControl
            || $rejectControl
            || $preferencesControl
            || preg_match(
                '/\b(?:pol[ií]tica de cookies?|cookie policy|utilizamos cookies?|usamos cookies?|este site utiliza cookies?|we use cookies?|this site uses cookies?)\b/u',
                $text,
            ) === 1
        );

        $evidence = [
            $this->signal(
                $document,
                EvidenceType::CookieNotice,
                $cookieNotice,
                'cookie_notice_context',
            ),
            $this->signal(
                $document,
                EvidenceType::CookieAcceptControl,
                $acceptControl,
                'accept_control_cookie_context',
            ),
            $this->signal(
                $document,
                EvidenceType::CookieRejectControl,
                $rejectControl,
                'reject_control_cookie_context',
            ),
            $this->signal(
                $document,
                EvidenceType::CookiePreferencesControl,
                $preferencesControl,
                'preferences_control_cookie_context',
            ),
            $this->signal(
                $document,
                EvidenceType::CookieCategoriesDisclosure,
                preg_match(
                    '/\b(?:categorias?|tipos?) de cookies\b|\bcookie categories?\b|\b(?:cookies? (?:necess[aá]rios|essenciais|anal[ií]ticos|de marketing|funcionais)|necessary cookies|analytics cookies|marketing cookies|functional cookies)\b/u',
                    $text,
                ) === 1,
                'cookie_categories_text',
            ),
            $this->signal(
                $document,
                EvidenceType::CookieThirdPartiesDisclosure,
                preg_match(
                    '/\bcookies? de terceiros\b|\bthird[- ]party cookies?\b|\bterceiros\b.{0,100}\bcookies?\b/u',
                    $text,
                ) === 1,
                'cookie_third_parties_text',
            ),
        ];

        $evidence[] = $this->nonEssentialStorageEvidence($document);
        $evidence[] = $this->thirdPartyRequestEvidence($document);

        return $evidence;
    }

    private function interactiveControlLabels(string $html): string
    {
        try {
            $crawler = new Crawler($html);
            $labels = [];
            foreach (
                $crawler->filter(
                    'button, a, input[type="button"], input[type="submit"], [role="button"]',
                ) as $node
            ) {
                if (!$node instanceof DOMElement) {
                    continue;
                }

                $parts = [trim($node->textContent)];
                foreach (['value', 'aria-label', 'title'] as $attribute) {
                    if ($node->hasAttribute($attribute)) {
                        $parts[] = trim($node->getAttribute($attribute));
                    }
                }

                foreach ($parts as $part) {
                    if ($part !== '') {
                        $labels[] = $part;
                    }
                }
            }
        } catch (\Throwable) {
            return '';
        }

        $text = mb_strtolower(
            html_entity_decode(implode(' ', $labels), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
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

        $browser = $this->browserMetadata($document);
        if ($browser === null) {
            return $this->dynamicUnknown(
                $document,
                EvidenceType::NonEssentialStorageBeforeConsent,
                'cookies_unavailable',
            );
        }

        /** @psalm-suppress MixedAssignment */
        $cookies = $browser['cookies'] ?? null;
        if (!is_array($cookies)) {
            return $this->dynamicUnknown(
                $document,
                EvidenceType::NonEssentialStorageBeforeConsent,
                'cookies_unavailable',
            );
        }

        $knownTracking = [];
        $unknownCount = 0;
        /** @psalm-suppress MixedAssignment */
        foreach ($cookies as $cookie) {
            if (!is_array($cookie) || !array_key_exists('name', $cookie)) {
                continue;
            }

            try {
                $name = Value::string($cookie['name'], 'browser.cookie.name');
            } catch (\UnexpectedValueException) {
                continue;
            }

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

        $browser = $this->browserMetadata($document);
        if ($browser === null) {
            return $this->dynamicUnknown(
                $document,
                EvidenceType::ThirdPartyRequestsBeforeConsent,
                'requests_unavailable',
            );
        }

        /** @psalm-suppress MixedAssignment */
        $requests = $browser['requests'] ?? null;
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
        /** @psalm-suppress MixedAssignment */
        foreach ($requests as $request) {
            if (!is_array($request) || !array_key_exists('url', $request)) {
                continue;
            }

            try {
                $requestUrl = Value::string($request['url'], 'browser.request.url');
            } catch (\UnexpectedValueException) {
                continue;
            }

            $host = parse_url($requestUrl, PHP_URL_HOST);
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


    /**
     * @return array<string,mixed>|null
     */
    private function browserMetadata(FetchedDocument $document): ?array
    {
        /** @psalm-suppress MixedAssignment */
        $browser = $document->metadata['browser'] ?? null;
        if (!is_array($browser)) {
            return null;
        }

        $normalized = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($browser as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
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
