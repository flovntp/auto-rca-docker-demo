<?php

namespace App\Invoice;

final readonly class PaymentReminder
{
    public function __construct(
        public string $to,
        public string $subject,
        public string $body,
    ) {
    }
}
