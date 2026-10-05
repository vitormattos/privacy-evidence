<?php

declare(strict_types=1);

namespace PrivacyEvidence\Review;

use DOMDocument;
use DOMXPath;
use PrivacyEvidence\Acquisition\FilesystemDocumentStore;

/** Builds reviewer material without changing detector evidence or visiting a website. */
final readonly class ReviewMaterial
{
    /**
     * @param list<array<string, mixed>> $resources
     * @param list<array<string, mixed>> $documents
     */
    public function __construct(
        private array $resources,
        private array $documents,
        private string $artifactDirectory,
    ) {
    }

    /** @param array<string, mixed> $package @return array<string, mixed> */
    public function enrich(array $package): array
    {
        $resources = [];
        foreach ($this->resources as $resource) {
            if (is_string($resource['id'] ?? null)) {
                $resources[$resource['id']] = $resource;
            }
        }
        $documents = [];
        foreach ($this->documents as $document) {
            if (is_string($document['artifactHash'] ?? null)) {
                $documents[$document['artifactHash']] = $document;
            }
        }
        $material = [];
        $cases = [];
        $store = new FilesystemDocumentStore($this->artifactDirectory);
        $inputCases = $package['cases'] ?? [];
        if (!is_array($inputCases)) {
            throw new \InvalidArgumentException('Package cases must be an array.');
        }
        foreach ($inputCases as $case) {
            if (!is_array($case)) {
                throw new \InvalidArgumentException('Package case must be an object.');
            }
            $resourceId = $case['resourceId'] ?? '';
            $hash = $case['artifactHash'] ?? '';
            $resource = is_string($resourceId) ? ($resources[$resourceId] ?? []) : [];
            $document = is_string($hash) ? ($documents[$hash] ?? []) : [];
            $reason = null;
            if (
                $resource === []
                || $document === []
                || ($document['finalUrl'] ?? null) !== ($case['sourceUrl'] ?? null)
                || ($document['resourceId'] ?? null) !== $resourceId
            ) {
                $reason = 'missing_provenance';
            } elseif (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
                $reason = 'invalid_hash';
            } else {
                if (!isset($material[$hash])) {
                    $body = $store->get($hash);
                    if ($body === null) {
                        $reason = 'missing_artifact';
                    } elseif (hash('sha256', $body) !== $hash) {
                        $reason = 'hash_mismatch';
                    } elseif (!in_array($document['mediaType'] ?? null, ['text/html', 'application/xhtml+xml', 'text/plain'], true)) {
                        $reason = 'unsupported_media';
                    } else {
                        $material[$hash] = $this->text($body, ($document['mediaType'] ?? null) === 'text/plain');
                    }
                }
                if ($reason === null && trim($material[$hash]['text'] ?? '') === '') {
                    $reason = 'empty_document';
                }
                if ($reason === null && in_array($case['evidenceType'] ?? null, [
                    'nonessential_storage_before_consent',
                    'third_party_requests_before_consent',
                ], true)) {
                    $reason = 'behavioral_material_required';
                }
            }
            /** @var array<string, mixed> $case */
            $case['reviewContext'] = [
                'resourceName' => is_string($resource['name'] ?? null) ? $resource['name'] : null,
                'resourceUrl' => is_string($resource['normalizedUrl'] ?? null) ? $resource['normalizedUrl'] : null,
                'fetchedAt' => is_string($document['fetchedAt'] ?? null) ? $document['fetchedAt'] : null,
                'truncated' => ($document['truncated'] ?? false) === true,
                'reason' => $reason,
            ];
            $cases[] = $case;
        }
        $package['cases'] = $cases;
        $package['reviewDocuments'] = $material === [] ? new \stdClass() : $material;
        $package['packageVersion'] = '1.1.0';

        return $package;
    }

    /** @return array{title: string, text: string} */
    private function text(string $body, bool $plain): array
    {
        if ($plain) {
            return ['title' => '', 'text' => $body];
        }
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);
            $titleValue = $xpath->evaluate('string(//title)');
            $title = is_string($titleValue) ? trim($titleValue) : '';
            $nodes = $xpath->query('//script|//style|//noscript|//head|//template');
            if ($nodes !== false) {
                foreach ($nodes as $node) {
                    $node->parentNode?->removeChild($node);
                }
            }
            $links = $xpath->query('//a[@href]');
            if ($links !== false) {
                foreach ($links as $node) {
                    if ($node instanceof \DOMElement) {
                        $node->appendChild($dom->createTextNode(' [' . $node->getAttribute('href') . ']'));
                    }
                }
            }
            $blocks = $xpath->query('//p|//div|//section|//article|//li|//h1|//h2|//h3|//tr|//br');
            if ($blocks !== false) {
                foreach ($blocks as $node) {
                    $node->appendChild($dom->createTextNode("\n"));
                }
            }
            $text = $dom->textContent;
            $text = preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text;
            $text = preg_replace('/\n\s*\n/u', "\n\n", $text) ?? $text;

            return ['title' => $title, 'text' => trim($text)];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
