<?php

declare(strict_types=1);

namespace PrivacyEvidence\Runtime;

use PDO;
use PrivacyEvidence\Acquisition\FilesystemDocumentStore;
use PrivacyEvidence\Acquisition\HttpFetcher;
use PrivacyEvidence\Browser\BrowserProvider;
use PrivacyEvidence\Pipeline\DefaultDetectorRegistry;
use PrivacyEvidence\Pipeline\PipelineConfig;
use PrivacyEvidence\Pipeline\ResearchPipeline;
use PrivacyEvidence\Queue\SqliteJobQueue;
use PrivacyEvidence\Review\SqliteReviewQueue;
use PrivacyEvidence\Run\SqliteRunStore;
use PrivacyEvidence\Storage\SqliteObservationStore;

final class RuntimeFactory
{
    public static function create(string $projectRoot): RuntimeContext
    {
        $derived = $projectRoot . '/data/derived';
        if (!is_dir($derived) && !mkdir($derived, 0700, true) && !is_dir($derived)) {
            throw new \RuntimeException('Unable to create data/derived directory.');
        }

        $pdo = new PDO('sqlite:' . $derived . '/privacy-evidence.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA busy_timeout = 1000');
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');

        $runs = new SqliteRunStore($pdo);
        $jobs = new SqliteJobQueue($pdo);
        $observations = new SqliteObservationStore($pdo);
        $reviews = new SqliteReviewQueue($pdo);

        return new RuntimeContext(
            runs: $runs,
            jobs: $jobs,
            observations: $observations,
            reviews: $reviews,
            artifactDirectory: $projectRoot . '/data/raw/artifacts',
        );
    }

    public static function pipeline(
        RuntimeContext $context,
        PipelineConfig $config,
        ?BrowserProvider $browser = null,
    ): ResearchPipeline {
        return new ResearchPipeline(
            runs: $context->runs,
            jobs: $context->jobs,
            observations: $context->observations,
            documents: new FilesystemDocumentStore($context->artifactDirectory),
            reviews: $context->reviews,
            fetcher: new HttpFetcher(),
            detectors: DefaultDetectorRegistry::create(),
            browser: $browser,
            config: $config,
        );
    }
}
