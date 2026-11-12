<?php

namespace App\Invoice;

final readonly class Invoice
{
    /**
     * @param list<float> $lines amounts of the invoice lines, before tax
     */
    public function __construct(
        public int $id,
        public string $customer,
        public array $lines,
    ) {
    }
}
