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
    public function itWaitsForAnotherWriterInsteadOfFailingWithDatabaseLocked(): void
    {
        $database = new Database($this->path);
        $database->createSchema();
        $recorder = new PaymentRecorder($database);

        $script = '$p = new PDO("sqlite:".$argv[1]); $p->exec("BEGIN IMMEDIATE"); echo "locked\n"; usleep(700000); $p->exec("COMMIT");';
        $process = proc_open([\PHP_BINARY, '-r', $script, $this->path], [1 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);

        try {
            self::assertSame("locked\n", fgets($pipes[1]));

            self::assertSame(10.0, $recorder->record(1, 10.0));
        } finally {
            fclose($pipes[1]);
            proc_close($process);
        }
        self::assertSame(['paid' => 10.0, 'payments_sum' => 10.0, 'payments' => 1], $recorder->balance(1));
    }
}
