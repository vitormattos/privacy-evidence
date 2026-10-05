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
                $busy = self::isBusy($exception);
                $misuseAfterBusy = $attempt > 0 && self::isStatementMisuse($exception);
                if ((!$busy && !$misuseAfterBusy) || $attempt >= 19) {
                    throw $exception;
                }

                // PDO SQLite can leave a prepared statement in a transient
                // SQLITE_MISUSE state after a busy/locked write. Resetting
                // the cursor and retrying is safe only after this retry path
                // has already observed contention; an isolated MISUSE remains
                // a programming error and is propagated.
                $statement->closeCursor();
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

    private static function isStatementMisuse(PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'bad parameter or other api misuse')
            || str_contains($message, 'api misuse');
    }

    private static function delayUs(int $attempt): int
    {
        return min(250_000, 10_000 * (1 << min($attempt, 5)));
    }
}
