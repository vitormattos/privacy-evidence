<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Analysis;

use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Analysis\Proportion;

final class ProportionTest extends TestCase
{
    public function testKeepsUnknownOutsideDenominatorExplicitly(): void
    {
        $p = new Proportion(15, 20, unknown: 3, unavailable: 2);

        self::assertSame(0.75, $p->value());
        self::assertSame(3, $p->unknown);
        self::assertSame(2, $p->unavailable);
    }
}
