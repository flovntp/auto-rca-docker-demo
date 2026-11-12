<?php

namespace App\Tests\Payment;

use App\Payment\Database;
use App\Payment\PaymentRecorder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaymentRecorderTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/payments-'.bin2hex(random_bytes(4)).'.db';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    #[Test]
    public function itAddsEachPaymentToWhatIsPaid(): void
    {
        $database = new Database($this->path);
        $database->createSchema();
        $recorder = new PaymentRecorder($database);

        self::assertSame(100.0, $recorder->record(1, 100.0));
        self::assertSame(145.5, $recorder->record(1, 45.5));
        self::assertSame(['paid' => 145.5, 'payments_sum' => 145.5, 'payments' => 2], $recorder->balance(1));
    }

    #[Test]
    public function itWaitsForAConcurrentWriterInsteadOfFailingWithDatabaseLocked(): void
    {
        $database = new Database($this->path);
        $database->createSchema();

        // Another connection holds the write lock for a moment.
        $other = new \PDO('sqlite:'.$this->path, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $other->exec('BEGIN IMMEDIATE');
        $other->exec('UPDATE invoice_balances SET paid = paid WHERE invoice_id = 1');

        $process = proc_open(
            [\PHP_BINARY, __DIR__.'/record_child.php', $this->path],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        usleep(700_000);
        $other->exec('COMMIT');

        $stderr = stream_get_contents($pipes[2]);
        stream_get_contents($pipes[1]);
        $exit = proc_close($process);

        self::assertSame(0, $exit, $stderr);
        self::assertSame(['paid' => 10.0, 'payments_sum' => 10.0, 'payments' => 1], (new PaymentRecorder($database))->balance(1));
    }
}
