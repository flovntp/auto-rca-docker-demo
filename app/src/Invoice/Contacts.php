<?php

namespace App\Invoice;

/**
 * The customers' billing contacts, kept in memory like the invoices.
 */
final class Contacts
{
    public function emailOf(string $customer): ?string
    {
        return match ($customer) {
            'Atelier Durand' => 'compta@atelier-durand.example',
            'Cave des Alpes' => 'factures@cave-des-alpes.example',
            // Billed by phone: no email address on file.
            'Boulangerie Martin' => null,
            default => null,
        };
    }
}
