<?php

namespace App\Tests\Triage;

use App\Triage\SourceCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SourceCodeTest extends TestCase
{
    private SourceCode $source;

    protected function setUp(): void
    {
        $this->source = new SourceCode(\dirname(__DIR__, 2));
    }

    #[Test]
    public function itReadsLinesOfTheApplicationsCodeWithTheirNumbers(): void
    {
        $lines = $this->source->read('src/Invoice/InvoiceSummary.php', 1, 3);

        self::assertSame("   1  <?php\n   2  \n   3  namespace App\\Invoice;", $lines);
    }

    #[Test]
    public function itListsTheApplicationsFiles(): void
    {
        self::assertStringContainsString('src/Payment/PaymentRecorder.php', $this->source->list());
        self::assertStringNotContainsString('vendor/', $this->source->list());
    }

    /** The tool is the agent's only access to the disk: secrets and the rest stay out of reach. */
    #[Test]
    #[DataProvider('outOfReach')]
    public function itRefusesFilesOutsideTheCode(string $path): void
    {
        self::assertStringStartsWith('Not readable', $this->source->read($path));
    }

    public static function outOfReach(): iterable
    {
        yield 'dotenv' => ['.env'];
        yield 'dependencies' => ['vendor/autoload.php'];
        yield 'climbing out' => ['src/../.env'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'outside the project' => ['../compose.yaml'];
        yield 'missing file' => ['src/Nope.php'];
    }
}
