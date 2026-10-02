<?php

declare(strict_types=1);

namespace PrivacyEvidence\Evidence;

use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Core\ObservationState;

abstract class AbstractTextDetector implements Detector
{
    /**
     * @return list<array{type: EvidenceType, patterns: list<non-empty-string>}>
     */
    abstract protected function patterns(): array;

    public function detect(FetchedDocument $document): array
    {
        $text = $this->normalizedText($document->body);
        $evidence = [];

        foreach ($this->patterns() as $definition) {
            $matches = [];
            foreach ($definition['patterns'] as $pattern) {
                if (preg_match($pattern, $text, $match) === 1) {
                    $matches[] = $match[0];
                }
            }

            $present = $matches !== [];
            $evidence[] = new PrivacyEvidence(
                type: $definition['type'],
                state: $present ? ObservationState::Present : ObservationState::Absent,
                resourceId: $document->resourceId,
                artifactHash: $document->sha256,
                sourceUrl: $document->finalUrl,
                detector: $this->name(),
                detectorVersion: $this->version(),
                method: 'rule_based_text',
                excerpt: $present ? mb_substr($matches[0], 0, 240) : null,
                confidence: $present ? 0.85 : 0.75,
                needsReview: false,
                attributes: ['matches' => count($matches)],
            );
        }

        return $evidence;
    }

    protected function normalizedText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtolower(trim($text));
    }
}
