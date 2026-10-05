<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Storage;

use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Storage\SqliteRetry;

final class SqliteRetryTest extends TestCase
{
    public function testBusyStatementIsResetBeforeRetry(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $attempts = 0;

        $statement
            ->expects(self::exactly(2))
            ->method('execute')
            ->with(['id' => 'job-1'])
            ->willReturnCallback(
                static function () use (&$attempts): bool {
                    $attempts++;
                    if ($attempts === 1) {
                        throw new PDOException(
                            'SQLSTATE[HY000]: General error: 5 database is locked',
                        );
                    }

                    return true;
                },
            );

        $statement
            ->expects(self::once())
            ->method('closeCursor')
            ->willReturn(true);

        SqliteRetry::execute($statement, ['id' => 'job-1']);

        self::assertSame(2, $attempts);
    }

    public function testNonBusyFailureIsNotRetried(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement
            ->expects(self::once())
            ->method('execute')
            ->willThrowException(new PDOException('SQLSTATE[HY000]: General error: 21 API misuse'));

        $statement->expects(self::never())->method('closeCursor');

        try {
            SqliteRetry::execute($statement, []);
            self::fail('Expected PDOException was not thrown.');
        } catch (PDOException $exception) {
            self::assertStringContainsString('API misuse', $exception->getMessage());
        }
    }
}
