<?php

namespace App\Invoice;

/**
 * A few invoices kept in memory: the demo needs no database.
 */
final class InvoiceRepository
{
    public function find(int $id): ?Invoice
    {
        return match ($id) {
            1 => new Invoice(1, 'Atelier Durand', [120.0, 80.0, 45.5]),
            2 => new Invoice(2, 'Boulangerie Martin', [300.0]),
            // A draft: created, no line added yet.
            3 => new Invoice(3, 'Cave des Alpes', []),
            default => null,
        };
    }
}
