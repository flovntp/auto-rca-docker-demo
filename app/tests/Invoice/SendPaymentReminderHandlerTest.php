<?php

namespace App\Tests\Invoice;

use App\Invoice\Contacts;
use App\Invoice\InvoiceRepository;
use App\Invoice\SendPaymentReminder;
use App\Invoice\SendPaymentReminderHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class SendPaymentReminderHandlerTest extends TestCase
{
    #[Test]
    public function itSendsTheReminderToTheCustomersBillingContact(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array<string, mixed>> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = $context;
            }
        };

        (new SendPaymentReminderHandler(new InvoiceRepository(), new Contacts(), $logger))(new SendPaymentReminder(1));

        self::assertSame('compta@atelier-durand.example', $logger->records[0]['to']);
        self::assertSame('Invoice 1: payment reminder', $logger->records[0]['subject']);
    }
}
