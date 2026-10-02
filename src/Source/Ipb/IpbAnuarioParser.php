<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source\Ipb;

use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\ResourceClassifier;
use PrivacyEvidence\Source\UrlNormalizer;
use Symfony\Component\DomCrawler\Crawler;

final readonly class IpbAnuarioParser
{
    public function __construct(
        private UrlNormalizer $normalizer = new UrlNormalizer(),
        private ResourceClassifier $classifier = new ResourceClassifier(),
    ) {
    }

    /**
     * @return list<ImportedResource>
     */
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);
        $resources = [];

        $blocks = $crawler->filter(
            'div[style^="font-family: Helvetica, Arial; background-color: rgba(0,0,0,0.05);"]',
        );

        foreach ($blocks as $index => $blockNode) {
            $block = new Crawler($blockNode);
            $divs = $block->filter('div');
            if ($divs->count() === 0) {
                continue;
            }

            $church = $this->parseChurch($divs);
            if ($church['name'] === '') {
                continue;
            }

            $pastor = $this->parsePastor($divs);
            $sourceValue = $church['website'];
            $normalized = $this->normalizer->normalize($sourceValue);

            $metadata = [
                'presbytery' => $church['presbytery'],
                'address' => $church['address'],
                'municipality' => $church['municipality'],
                'state' => $church['state'],
                'postal_code' => $church['postal_code'],
                'phone' => $church['phone'],
                'email' => $church['email'],
                'pastor_name' => $pastor['name'] ?? null,
                'pastor_phone' => $pastor['phone'] ?? null,
                'pastor_mobile' => $pastor['mobile'] ?? null,
                'pastor_email' => $pastor['email'] ?? null,
                'legacy_block_index' => $index,
            ];

            $stableId = hash(
                'sha256',
                implode('|', [
                    $church['name'],
                    $church['presbytery'],
                    $church['municipality'],
                    $church['state'],
                ]),
            );

            $resources[] = new ImportedResource(
                id: 'ipb-' . substr($stableId, 0, 20),
                name: $church['name'],
                sourceValue: $sourceValue,
                normalizedUrl: $normalized,
                type: $this->classifier->classify($sourceValue, $normalized),
                metadata: $metadata,
            );
        }

        return $resources;
    }

    /**
     * @return array{
     *   name:string,presbytery:string,address:string,municipality:string,state:string,
     *   postal_code:string,phone:string,email:string,website:string
     * }
     */
    private function parseChurch(Crawler $divs): array
    {
        $churchDiv = $divs->reduce(
            static fn (Crawler $node): bool => $node->attr('style') === 'padding: 15px;',
        )->first();

        if ($churchDiv->count() === 0) {
            return $this->emptyChurch();
        }

        $name = trim($churchDiv->filter('big > b')->text(''));
        $presbytery = trim($churchDiv->filter('b')->eq(1)->text(''));

        $address = '';
        $municipality = '';
        $state = '';
        $postalCode = '';
        $phone = '';
        $email = '';
        $website = '';

        $churchHtml = (string) $churchDiv->html('');

        if (preg_match('/<br\s*\/?><br\s*\/?>\s*(?<address>[^<]+)<br\s*\/?>/i', $churchHtml, $match) === 1) {
            $address = trim($match['address']);

            if (preg_match('/ (?<municipality>[^\-\/\n]+)\s*\/\s*(?<state>[A-Z]{2})$/', $address, $place) === 1) {
                $municipality = trim($place['municipality']);
                $state = trim($place['state']);
                $address = trim((string) preg_replace('/\s*' . preg_quote($place[0], '/') . '$/', '', $address));
                $address = trim((string) preg_replace('/\s*-\s*$/', '', $address));
            }
        }

        if (preg_match('/<br\s*\/?>CEP:\s*(?<postal>[\d.\-]*)<br\s*\/?>/i', $churchHtml, $match) === 1) {
            $postalCode = trim($match['postal']);
        }

        foreach ($churchDiv->filter('a[href]') as $linkNode) {
            $link = new Crawler($linkNode);
            $href = trim((string) $link->attr('href'));
            if ($href === '') {
                continue;
            }

            if (str_starts_with($href, 'tel:')) {
                $phone = $this->normalizePhone(substr($href, 4));
            } elseif (str_starts_with($href, 'mailto:')) {
                $email = trim(substr($href, 7));
            } elseif (preg_match('~^(?:https?://|www\.)~i', $href) === 1) {
                $website = $href;
            }
        }

        return [
            'name' => $name,
            'presbytery' => $presbytery,
            'address' => $address,
            'municipality' => $municipality,
            'state' => $state,
            'postal_code' => $postalCode,
            'phone' => $phone,
            'email' => $email,
            'website' => $website,
        ];
    }

    /**
     * @return array{name:string,phone:string,mobile:string,email:string}|null
     */
    private function parsePastor(Crawler $divs): ?array
    {
        $pastorDiv = $divs->reduce(
            static fn (Crawler $node): bool => str_contains(
                $node->attr('style') ?? '',
                'background-color: rgba(0,0,0,0.05);',
            ),
        )->first();

        if ($pastorDiv->count() === 0) {
            return null;
        }

        $html = (string) $pastorDiv->html('');

        return [
            'name' => trim($pastorDiv->filter('b > small')->text('')),
            'phone' => $this->matchPhone($html, 'Tel'),
            'mobile' => $this->matchPhone($html, 'Cel'),
            'email' => $this->matchEmail($html),
        ];
    }

    private function matchPhone(string $html, string $label): string
    {
        if (preg_match('/' . preg_quote($label, '/') . ':\s*<a href="tel:(?<phone>[^"]+)"/i', $html, $match) !== 1) {
            return '';
        }

        return $this->normalizePhone($match['phone']);
    }

    private function matchEmail(string $html): string
    {
        if (preg_match('/Email:\s*<a href="mailto:(?<email>[^"]+)"/i', $html, $match) !== 1) {
            return '';
        }

        return trim($match['email']);
    }

    private function normalizePhone(string $value): string
    {
        $value = str_replace('%20', ' ', $value);

        return trim((string) preg_replace('/[^\d\s+\-()]/', '', $value));
    }

    /**
     * @return array{
     *   name:string,presbytery:string,address:string,municipality:string,state:string,
     *   postal_code:string,phone:string,email:string,website:string
     * }
     */
    private function emptyChurch(): array
    {
        return [
            'name' => '',
            'presbytery' => '',
            'address' => '',
            'municipality' => '',
            'state' => '',
            'postal_code' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
        ];
    }
}
