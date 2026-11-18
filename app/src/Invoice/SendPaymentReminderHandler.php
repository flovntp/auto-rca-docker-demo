<?php

namespace App\Invoice;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * In the worker: writes the reminder and sends it. The demo sends no email,
 * the reminder is logged.
 */
#[AsMessageHandler]
final readonly class SendPaymentReminderHandler
{
    public function __construct(
        private InvoiceRepository $invoices,
        private Contacts $contacts,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendPaymentReminder $message): void
    {
        $invoice = $this->invoices->find($message->invoiceId);
        if (null === $invoice) {
            return;
        }

        $reminder = new PaymentReminder(
            to: $this->contacts->emailOf($invoice->customer),
            subject: \sprintf('Invoice %d: payment reminder', $invoice->id),
            body: \sprintf('Dear %s, invoice %d (%.2f EUR) is still unpaid.', $invoice->customer, $invoice->id, array_sum($invoice->lines)),
        );

        $this->logger->info('Payment reminder sent to {to}: {subject}', ['to' => $reminder->to, 'subject' => $reminder->subject]);
    }
}
