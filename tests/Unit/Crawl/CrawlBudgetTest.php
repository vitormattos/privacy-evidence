<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Crawl;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Crawl\CrawlBudget;

final class CrawlBudgetTest extends TestCase
{
    public function testDefaultBudgetIsPartOfThePublicContract(): void
    {
        $budget = new CrawlBudget();

        self::assertSame(20, $budget->maxPages);
        self::assertSame(3, $budget->maxDepth);
        self::assertSame(5_000_000, $budget->maxBytes);
        self::assertSame(0, $budget->maxDurationSeconds);
        self::assertSame(3, $budget->maxBrowserPages);
    }

    public function testZeroBudgetValuesAreAllowed(): void
    {
        $budget = new CrawlBudget(0, 0, 0, 0, 0);

        self::assertSame(0, $budget->maxPages);
        self::assertSame(0, $budget->maxDepth);
        self::assertSame(0, $budget->maxBytes);
        self::assertSame(0, $budget->maxDurationSeconds);
        self::assertSame(0, $budget->maxBrowserPages);
    }

    /**
     * @param array{int,int,int,int,int} $values
     */
    #[DataProvider('negativeBudgets')]
    public function testRejectsNegativeValues(array $values): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CrawlBudget(...$values);
    }

    /**
     * @return iterable<string,array{array{int,int,int,int,int}}>
     */
    public static function negativeBudgets(): iterable
    {
        yield 'pages' => [[-1, 0, 0, 0, 0]];
        yield 'depth' => [[0, -1, 0, 0, 0]];
        yield 'bytes' => [[0, 0, -1, 0, 0]];
        yield 'duration' => [[0, 0, 0, -1, 0]];
        yield 'browser pages' => [[0, 0, 0, 0, -1]];
    }
}
