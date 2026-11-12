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

        return [
            'id' => $invoice->id,
            'customer' => $invoice->customer,
            'total' => round($total, 2),
            'lines' => \count($invoice->lines),
            'average_line' => [] === $invoice->lines ? 0.0 : round($total / \count($invoice->lines), 2),
        ];
    }
}
