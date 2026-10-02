<?php

declare(strict_types=1);

namespace PrivacyEvidence\Crawl;

use DOMElement;
use PrivacyEvidence\Acquisition\FetchedDocument;
use Symfony\Component\DomCrawler\Crawler;

final class LinkDiscoverer
{
    public const VERSION = '1.0.0';

    /** @var list<string> */
    private array $privacyTerms;

    /** @var list<string> */
    private array $controlTerms;

    /** @var list<string> */
    private array $supportingTerms;

    /**
     * @param list<string>|null $privacyTerms
     * @param list<string>|null $controlTerms
     * @param list<string>|null $supportingTerms
     */
    public function __construct(
        ?array $privacyTerms = null,
        ?array $controlTerms = null,
        ?array $supportingTerms = null,
        private readonly string $version = self::VERSION,
    ) {
        $this->privacyTerms = $privacyTerms ?? [
            'privacy', 'privacidade', 'proteção de dados', 'protecao-de-dados', 'lgpd', 'gdpr',
        ];
        $this->controlTerms = $controlTerms ?? [
            'cookie', 'dpo', 'encarregado', 'direitos', 'rights',
        ];
        $this->supportingTerms = $supportingTerms ?? [
            'contact', 'contato', 'about', 'sobre', 'terms', 'termos', 'legal',
        ];
    }

    /**
     * @return list<CandidateUrl>
     */
    public function discover(FetchedDocument $document): array
    {
        if (!str_contains(strtolower($document->mediaType), 'html')) {
            return [];
        }

        $crawler = new Crawler($document->body, $document->finalUrl);
        $candidates = [];

        foreach ($crawler->filter('a[href]') as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $href = trim($node->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            try {
                $absolute = (new Crawler($node, $document->finalUrl))
                    ->filter('a')
                    ->link()
                    ->getUri();
            } catch (\Throwable) {
                continue;
            }

            $absolute = $this->withoutFragment($absolute);
            $current = $this->withoutFragment($document->finalUrl);
            if (
                $absolute === ''
                || $absolute === $current
                || !$this->sameHost($current, $absolute)
            ) {
                continue;
            }

            $text = trim($node->textContent);
            [$priority, $reason] = $this->priority($absolute, strtolower($text));
            $candidates[$absolute] = new CandidateUrl(
                url: $absolute,
                priority: $priority,
                reason: $reason,
                sourceUrl: $document->finalUrl,
                anchorText: $text,
                ruleVersion: $this->version,
            );
        }

        $result = array_values($candidates);
        usort(
            $result,
            static fn (CandidateUrl $a, CandidateUrl $b): int => $b->priority <=> $a->priority
                ?: strcmp($a->url, $b->url),
        );

        return $result;
    }

    private function withoutFragment(string $url): string
    {
        $fragment = strpos($url, '#');

        return $fragment === false ? $url : substr($url, 0, $fragment);
    }

    private function sameHost(string $base, string $candidate): bool
    {
        $baseHost = parse_url($base, PHP_URL_HOST);
        $candidateHost = parse_url($candidate, PHP_URL_HOST);

        return is_string($baseHost)
            && is_string($candidateHost)
            && strtolower($baseHost) === strtolower($candidateHost);
    }

    /**
     * @return array{int, string}
     */
    private function priority(string $url, string $text): array
    {
        $haystack = strtolower($url . ' ' . $text);

        foreach ($this->privacyTerms as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return [100, 'privacy'];
            }
        }

        foreach ($this->controlTerms as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return [80, 'privacy_control'];
            }
        }

        foreach ($this->supportingTerms as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return [50, 'supporting'];
            }
        }

        return [10, 'other_internal'];
    }
}
