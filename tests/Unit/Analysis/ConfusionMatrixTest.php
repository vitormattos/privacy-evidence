<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\ConfusionMatrix;

final class ConfusionMatrixTest extends TestCase
{
    public function testMetrics(): void
    {
        $m = new ConfusionMatrix(8, 2, 7, 3);

        self::assertEqualsWithDelta(0.8, $m->precision(), 0.0001);
        self::assertEqualsWithDelta(8 / 11, $m->recall(), 0.0001);
        self::assertEqualsWithDelta(0.7619047, $m->f1(), 0.0001);
        self::assertSame(11, $m->support());
    }
}
