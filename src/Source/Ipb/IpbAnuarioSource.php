<?php

declare(strict_types=1);

namespace PrivacyEvidence\Source\Ipb;

use PrivacyEvidence\Source\ImportedResource;
use PrivacyEvidence\Source\SourceAdapter;
use PrivacyEvidence\Source\SourceSnapshot;

final readonly class IpbAnuarioSource implements SourceAdapter
{
    public function __construct(
        private string $html,
        private string $capturedAt,
        private string $location,
        private IpbAnuarioParser $parser = new IpbAnuarioParser(),
    ) {
    }

    public static function fromFile(string $path, ?IpbAnuarioParser $parser = null): self
    {
        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException(sprintf('Unable to read IPB Anuário snapshot: %s', $path));
        }

        $mtime = filemtime($path);

        return new self(
            html: $html,
            capturedAt: gmdate(DATE_ATOM, $mtime === false ? 0 : $mtime),
            location: $path,
            parser: $parser ?? new IpbAnuarioParser(),
        );
    }

    public function sourceId(): string
    {
        return 'ipb-icalvinus';
    }

    /**
     * @return iterable<ImportedResource>
     */
    public function resources(): iterable
    {
        yield from $this->parser->parse($this->html);
    }

    public function snapshot(): SourceSnapshot
    {
        return new SourceSnapshot(
            sourceId: $this->sourceId(),
            capturedAt: $this->capturedAt,
            sha256: hash('sha256', $this->html),
            mediaType: 'text/html',
            location: $this->location,
        );
    }
}
