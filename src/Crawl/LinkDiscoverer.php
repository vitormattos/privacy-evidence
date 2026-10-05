<?php

declare(strict_types=1);

namespace PrivacyEvidence\Crawl;

use DOMElement;
use PrivacyEvidence\Acquisition\FetchedDocument;
use PrivacyEvidence\Source\UrlNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final class LinkDiscoverer
{
    public const VERSION = '1.3.0';

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
        private readonly UrlNormalizer $urlNormalizer = new UrlNormalizer(),
    ) {
        $this->privacyTerms = $privacyTerms ?? [
            'privacy', 'privacidade', 'política de privacidade', 'politica de privacidade',
            'proteção de dados', 'protecao de dados', 'proteção de dados pessoais',
            'protecao de dados pessoais', 'dados pessoais', 'lgpd', 'gdpr',
            'data protection', 'privacy notice',
        ];
        $this->controlTerms = $controlTerms ?? [
            'cookie', 'cookies', 'dpo', 'encarregado', 'direitos', 'rights',
            'titular', 'consentimento', 'consent',
        ];
        $this->supportingTerms = $supportingTerms ?? [
            'contact', 'contato', 'fale conosco', 'about', 'sobre', 'quem somos',
            'terms', 'termos', 'legal', 'institucional',
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

            $absolute = $this->urlNormalizer->normalize(
                $this->withoutTrackingParameters(
                    $this->withoutFragment($absolute),
                ),
            );
            $current = $this->urlNormalizer->normalize(
                $this->withoutFragment($document->finalUrl),
            );
            if (
                $absolute === null
                || $current === null
                || $absolute === $current
                || !$this->sameHost($current, $absolute)
            ) {
                continue;
            }

            $text = trim($node->textContent);
            [$priority, $reason] = $this->priority($absolute, $text);
            $candidate = new CandidateUrl(
                url: $absolute,
                priority: $priority,
                reason: $reason,
                sourceUrl: $document->finalUrl,
                anchorText: $text,
                ruleVersion: $this->version,
            );
            $existing = $candidates[$absolute] ?? null;
            if ($existing === null || $candidate->priority > $existing->priority) {
                $candidates[$absolute] = $candidate;
            }
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
        if (!is_string($baseHost) || !is_string($candidateHost)) {
            return false;
        }

        return $this->canonicalHost($baseHost) === $this->canonicalHost($candidateHost);
    }

    private function canonicalHost(string $host): string
    {
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private function withoutTrackingParameters(string $url): string
    {
        $question = strpos($url, '?');
        if ($question === false) {
            return $url;
        }

        $base = substr($url, 0, $question);
        $query = substr($url, $question + 1);
        $kept = [];

        foreach (explode('&', $query) as $parameter) {
            if ($parameter === '') {
                continue;
            }

            $key = rawurldecode(explode('=', $parameter, 2)[0]);
            $normalizedKey = strtolower($key);
            if (
                str_starts_with($normalizedKey, 'utm_')
                || in_array(
                    $normalizedKey,
                    ['fbclid', 'gclid', 'dclid', 'msclkid', 'mc_cid', 'mc_eid', '_ga'],
                    true,
                )
            ) {
                continue;
            }

            $kept[] = $parameter;
        }

        return $kept === [] ? $base : $base . '?' . implode('&', $kept);
    }

    /**
     * @return array{int, string}
     */
    private function priority(string $url, string $text): array
    {
        $haystack = mb_strtolower(
            html_entity_decode(rawurldecode($url) . ' ' . $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'UTF-8',
        );

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
