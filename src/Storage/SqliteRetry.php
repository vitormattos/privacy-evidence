<?php

declare(strict_types=1);

namespace PrivacyEvidence\Storage;

use PDOException;
use PDOStatement;

final class SqliteRetry
{
    /**
     * @param array<array-key,mixed> $parameters
     */
    public static function execute(PDOStatement $statement, array $parameters): void
    {
        $attempt = 0;

        while (true) {
            try {
                $statement->execute($parameters);
                return;
            } catch (PDOException $exception) {
                if (!self::isBusy($exception) || $attempt >= 19) {
                    throw $exception;
                }

                usleep(self::delayUs($attempt));
                $attempt++;
            }
        }
    }

    public static function isBusy(PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'database is locked')
            || str_contains($message, 'database is busy');
    }

    private static function delayUs(int $attempt): int
    {
        return min(250_000, 10_000 * (1 << min($attempt, 5)));
    }
}
