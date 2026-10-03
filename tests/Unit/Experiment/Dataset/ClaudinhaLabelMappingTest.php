<?php

declare(strict_types=1);

namespace PrivacyEvidence\Tests\Unit\Experiment\Dataset;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PrivacyEvidence\Evidence\EvidenceType;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelDisposition;
use PrivacyEvidence\Experiment\Dataset\ClaudinhaLabelMapping;

final class ClaudinhaLabelMappingTest extends TestCase
{
    public function testEveryPublishedUpstreamLabelHasAnExplicitDisposition(): void
    {
        $mapping = new ClaudinhaLabelMapping();

        self::assertCount(27, $mapping->labels());

        foreach ($mapping->labels() as $label) {
            $result = $mapping->map($label);

            self::assertNotSame('', $result['rationale']);
            self::assertContains(
                $result['disposition'],
                ClaudinhaLabelDisposition::cases(),
            );
        }
    }

    /**
     * @return iterable<string,array{
     *   0:string,
     *   1:ClaudinhaLabelDisposition,
     *   2:list<EvidenceType>
     * }>
     */
    public static function representativeMappings(): iterable
    {
        yield 'controller' => [
            'Controller identification',
            ClaudinhaLabelDisposition::Mappable,
            [EvidenceType::ControllerIdentity],
        ];
        yield 'dpo combined category' => [
            'ID and contact DPO',
            ClaudinhaLabelDisposition::Mappable,
            [EvidenceType::DpoIdentity, EvidenceType::DpoContact],
        ];
        yield 'specific right remains partial' => [
            'Access to data',
            ClaudinhaLabelDisposition::PartiallyMappable,
            [EvidenceType::RightsDisclosure],
        ];
        yield 'cookies do not imply controls' => [
            'Cookies',
            ClaudinhaLabelDisposition::PartiallyMappable,
            [EvidenceType::CookieNotice],
        ];
        yield 'legal quality label excluded' => [
            'Take it or leave it',
            ClaudinhaLabelDisposition::Excluded,
            [],
        ];
    }

    /**
     * @param list<EvidenceType> $types
     */
    #[DataProvider('representativeMappings')]
    public function testRepresentativeMapping(
        string $label,
        ClaudinhaLabelDisposition $disposition,
        array $types,
    ): void {
        $result = (new ClaudinhaLabelMapping())->map($label);

        self::assertSame($disposition, $result['disposition']);
        self::assertSame($types, $result['evidenceTypes']);
    }

    public function testUnknownLabelFailsClosed(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ClaudinhaLabelMapping())->map('Unknown upstream category');
    }

    public function testMappingVersionIsExplicit(): void
    {
        self::assertSame('1.0.0', ClaudinhaLabelMapping::VERSION);
    }
}
