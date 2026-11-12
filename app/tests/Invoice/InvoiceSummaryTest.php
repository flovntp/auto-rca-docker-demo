<?php

namespace App\Tests\Invoice;

use App\Invoice\Invoice;
use App\Invoice\InvoiceSummary;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InvoiceSummaryTest extends TestCase
{
    #[Test]
    public function itSumsAndAveragesTheLines(): void
    {
        $summary = (new InvoiceSummary())->summarize(new Invoice(1, 'Atelier Durand', [120.0, 80.0, 45.5]));

        self::assertSame(245.5, $summary['total']);
        self::assertSame(3, $summary['lines']);
        self::assertSame(81.83, $summary['average_line']);
    }

    #[Test]
    public function itSummarizesADraftWithoutLines(): void
    {
        $summary = (new InvoiceSummary())->summarize(new Invoice(3, 'Cave des Alpes', []));

        self::assertSame(0.0, $summary['total']);
        self::assertSame(0, $summary['lines']);
        self::assertSame(0.0, $summary['average_line']);
    }
}
