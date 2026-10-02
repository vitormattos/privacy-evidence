<?php

declare(strict_types=1);

namespace PrivacyEvidence\Crawl;

use DOMElement;
use PrivacyEvidence\Acquisition\FetchedDocument;
use Symfony\Component\DomCrawler\Crawler;

final class LinkDiscoverer
{
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

            if (!$this->sameHost($document->finalUrl, $absolute)) {
                continue;
            }

            $text = strtolower(trim($node->textContent));
            [$priority, $reason] = $this->priority($absolute, $text);
            $candidates[$absolute] = new CandidateUrl($absolute, $priority, $reason);
        }

        $result = array_values($candidates);
        usort(
            $result,
            static fn (CandidateUrl $a, CandidateUrl $b): int => $b->priority <=> $a->priority
                ?: strcmp($a->url, $b->url),
        );

        return $result;
    }

    private function sameHost(string $base, string $candidate): bool
    {
        return strtolower((string) parse_url($base, PHP_URL_HOST))
            === strtolower((string) parse_url($candidate, PHP_URL_HOST));
    }

    /**
     * @return array{int, string}
     */
    private function priority(string $url, string $text): array
    {
        $haystack = strtolower($url . ' ' . $text);

        foreach (['privacy', 'privacidade', 'proteção de dados', 'protecao-de-dados', 'lgpd', 'gdpr'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return [100, 'privacy'];
            }
        }

        foreach (['cookie', 'dpo', 'encarregado', 'direitos', 'rights'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return [80, 'privacy_control'];
            }
        }

        foreach (['contact', 'contato', 'about', 'sobre', 'terms', 'termos', 'legal'] as $needle) {
            if (str_contains($haystack, $needle)) {
                return [50, 'supporting'];
            }
        }

        return [10, 'other_internal'];
    }
}
