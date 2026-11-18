<?php

namespace App\Invoice;

/**
 * Remind a customer of an unpaid invoice. Sent by the worker, not during the
 * request.
 */
final readonly class SendPaymentReminder
{
    public function __construct(public int $invoiceId)
    {
    }
}
