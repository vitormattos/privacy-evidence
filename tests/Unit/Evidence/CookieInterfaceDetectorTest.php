<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Evidence;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;
use PrivacyEvidence\Evidence\Detector\CookieInterfaceDetector;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Evidence\PrivacyEvidence;

final class CookieInterfaceDetectorTest extends TestCase
{
    public function testSeparatesAllStaticCookieControlsWithExactMetadata(): void
    {
        $byType = $this->byType(
            (new CookieInterfaceDetector())->detect(
                $this->document(
                    '<div>Cookies <button>Aceitar</button><button>Rejeitar</button>'
                    . '<button>Preferências</button>'
                    . '<p>Categorias de cookies: necessários e analíticos. Cookies de terceiros.</p></div>',
                ),
            ),
        );

        foreach (
            [
                EvidenceType::CookieNotice,
                EvidenceType::CookieAcceptControl,
                EvidenceType::CookieRejectControl,
                EvidenceType::CookiePreferencesControl,
                EvidenceType::CookieCategoriesDisclosure,
                EvidenceType::CookieThirdPartiesDisclosure,
            ] as $type
        ) {
            self::assertSame(ObservationState::Present, $byType[$type->value]->state);
            self::assertSame(0.8, $byType[$type->value]->confidence);
            self::assertFalse($byType[$type->value]->needsReview);
        }

        self::assertSame('cookie_term', $byType[EvidenceType::CookieNotice->value]->method);
        self::assertSame(
            'accept_control_text',
            $byType[EvidenceType::CookieAcceptControl->value]->method,
        );
        self::assertSame(
            'reject_control_text',
            $byType[EvidenceType::CookieRejectControl->value]->method,
        );
        self::assertSame(
            'preferences_control_text',
            $byType[EvidenceType::CookiePreferencesControl->value]->method,
        );
        self::assertSame(
            'cookie_categories_text',
            $byType[EvidenceType::CookieCategoriesDisclosure->value]->method,
        );
        self::assertSame(
            'cookie_third_parties_text',
            $byType[EvidenceType::CookieThirdPartiesDisclosure->value]->method,
        );
    }

    public function testMissingStaticControlsAreAbsentAndNeedReview(): void
    {
        $byType = $this->byType(
            (new CookieInterfaceDetector())->detect($this->document('<p>Hello</p>')),
        );

        foreach (
            [
                EvidenceType::CookieNotice,
                EvidenceType::CookieAcceptControl,
                EvidenceType::CookieRejectControl,
                EvidenceType::CookiePreferencesControl,
                EvidenceType::CookieCategoriesDisclosure,
                EvidenceType::CookieThirdPartiesDisclosure,
            ] as $type
        ) {
            self::assertSame(ObservationState::Absent, $byType[$type->value]->state);
            self::assertSame(0.65, $byType[$type->value]->confidence);
            self::assertTrue($byType[$type->value]->needsReview);
        }
    }

    public function testHttpAcquisitionMarksDynamicSignalsUnknownWithoutHumanReview(): void
    {
        $byType = $this->byType(
            (new CookieInterfaceDetector())->detect($this->document('<p>Cookies</p>')),
        );

        foreach (
            [
                EvidenceType::NonEssentialStorageBeforeConsent,
                EvidenceType::ThirdPartyRequestsBeforeConsent,
            ] as $type
        ) {
            $item = $byType[$type->value];
            self::assertSame(ObservationState::Unknown, $item->state);
            self::assertSame('browser_observation_required', $item->method);
            self::assertSame(0.0, $item->confidence);
            self::assertFalse($item->needsReview);
        }
    }

    public function testBrowserWithoutUsableMetadataMarksDynamicSignalsUnknown(): void
    {
        $byType = $this->byType(
            (new CookieInterfaceDetector())->detect(
                $this->document('<p>Cookies</p>', 'browser', ['browser' => 'invalid']),
            ),
        );

        self::assertSame(
            'cookies_unavailable',
            $byType[EvidenceType::NonEssentialStorageBeforeConsent->value]->method,
        );
        self::assertSame(
            'requests_unavailable',
            $byType[EvidenceType::ThirdPartyRequestsBeforeConsent->value]->method,
        );
    }

    public function testBrowserKnownTrackingCookieIsPresentAndTraceable(): void
    {
        $byType = $this->byType(
            (new CookieInterfaceDetector())->detect(
                $this->document(
                    '<p>Cookies</p>',
                    'browser',
                    [
                        'browser' => [
                            'cookies' => [
                                ['name' => '_ga_ABC'],
                                ['name' => 'session'],
                            ],
                            'requests' => [],
                        ],
                    ],
                ),
            ),
        );

        $item = $byType[EvidenceType::NonEssentialStorageBeforeConsent->value];
        self::assertSame(ObservationState::Present, $item->state);
        self::assertSame('browser_cookie_before_interaction', $item->method);
        self::assertSame(0.9, $item->confidence);
        self::assertFalse($item->needsReview);
        self::assertSame(1, $item->attributes['knownTrackingCookies']);
        self::assertSame('_ga_ABC', $item->attributes['sampleCookie']);
    }

    public function testBrowserUnknownCookieNeedsReviewAndNoCookiesIsAbsent(): void
    {
        $detector = new CookieInterfaceDetector();

        $unknown = $this->byType(
            $detector->detect(
                $this->document(
                    '<p>Cookies</p>',
                    'browser',
                    ['browser' => ['cookies' => [['name' => 'custom']], 'requests' => []]],
                ),
            ),
        )[EvidenceType::NonEssentialStorageBeforeConsent->value];

        self::assertSame(ObservationState::Unknown, $unknown->state);
        self::assertSame(0.5, $unknown->confidence);
        self::assertTrue($unknown->needsReview);
        self::assertSame(['unclassifiedCookies' => 1], $unknown->attributes);

        $absent = $this->byType(
            $detector->detect(
                $this->document(
                    '<p>Cookies</p>',
                    'browser',
                    ['browser' => ['cookies' => [], 'requests' => []]],
                ),
            ),
        )[EvidenceType::NonEssentialStorageBeforeConsent->value];

        self::assertSame(ObservationState::Absent, $absent->state);
        self::assertSame(0.95, $absent->confidence);
        self::assertFalse($absent->needsReview);
    }

    public function testBrowserThirdPartyRequestsAreDetectedCaseInsensitively(): void
    {
        $byType = $this->byType(
            (new CookieInterfaceDetector())->detect(
                $this->document(
                    '<p>Cookies</p>',
                    'browser',
                    [
                        'browser' => [
                            'cookies' => [],
                            'requests' => [
                                ['url' => 'https://EXAMPLE.test/app.js'],
                                ['url' => 'https://tracker.test/pixel'],
                            ],
                        ],
                    ],
                ),
            ),
        );

        $item = $byType[EvidenceType::ThirdPartyRequestsBeforeConsent->value];
        self::assertSame(ObservationState::Present, $item->state);
        self::assertSame('browser_requests_before_interaction', $item->method);
        self::assertSame(0.9, $item->confidence);
        self::assertFalse($item->needsReview);
        self::assertSame(1, $item->attributes['thirdPartyRequestCount']);
        self::assertSame('tracker.test', $item->attributes['sampleThirdPartyHost']);
    }

    /**
     * @param list<PrivacyEvidence> $evidence
     * @return array<string, PrivacyEvidence>
     */
    private function byType(array $evidence): array
    {
        $byType = [];
        foreach ($evidence as $item) {
            $byType[$item->type->value] = $item;
        }

        self::assertCount(8, $byType);

        return $byType;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    private function document(
        string $body,
        string $mode = 'http',
        array $metadata = [],
    ): FetchedDocument {
        return new FetchedDocument(
            'x',
            'https://example.test',
            'https://example.test',
            200,
            'text/html',
            $body,
            '2026-10-02T00:00:00Z',
            $mode,
            false,
            $metadata,
        );
    }
}
