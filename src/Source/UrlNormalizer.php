<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source;

final class UrlNormalizer
{
    public function normalize(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('~^(?<scheme>[a-z][a-z0-9+.-]*):~i', $value, $match) === 1) {
            $scheme = strtolower($match['scheme']);
            $looksLikeHostWithPort = preg_match('~^[^/:]+:\d+(?:/|$)~', $value) === 1;

            if (!in_array($scheme, ['http', 'https'], true) && !$looksLikeHostWithPort) {
                return null;
            }
        }

        if (!preg_match('~^https?://~i', $value)) {
            $value = 'https://' . $value;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($value);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        // Userinfo in a website field is almost always an e-mail address or
        // malformed source value (for example http://name@gmail.com). Keeping
        // it would silently transform the source into the provider host.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        if ($host === '' || preg_match('/\s/u', $host) === 1) {
            return null;
        }

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if (!$isIp && !str_contains($host, '.')) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $normalized = sprintf('%s://%s%s%s%s', $scheme, $host, $port, $path, $query);

        return filter_var($normalized, FILTER_VALIDATE_URL) === false ? null : $normalized;
    }
}
