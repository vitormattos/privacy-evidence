<?php

declare(strict_types=1);

namespace PrivacyEvidence\Acquisition;

final readonly class FilesystemDocumentStore implements DocumentStore
{
    public function __construct(private string $directory)
    {
    }

    public function put(FetchedDocument $document): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Unable to create document store.');
        }

        $path = $this->path($document->sha256);
        if (!is_file($path) && file_put_contents($path, $document->body, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to persist fetched artifact.');
        }

        return $path;
    }

    public function get(string $sha256): ?string
    {
        $path = $this->path($sha256);
        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    private function path(string $sha256): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new \InvalidArgumentException('Invalid SHA-256.');
        }

        return rtrim($this->directory, '/') . '/' . $sha256 . '.bin';
    }
}
