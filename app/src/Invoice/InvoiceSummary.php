<?php

namespace App\Invoice;

final class InvoiceSummary
{
    /**
     * @return array{id: int, customer: string, total: float, lines: int, average_line: float}
     */
    public function summarize(Invoice $invoice): array
    {
        $total = array_sum($invoice->lines);
        $count = \count($invoice->lines);

        return [
            'id' => $invoice->id,
            'customer' => $invoice->customer,
            'total' => round($total, 2),
            'lines' => $count,
            // A draft invoice has no line yet: its average is 0, not undefined.
            'average_line' => 0 === $count ? 0.0 : round($total / $count, 2),
        ];
    }
}
