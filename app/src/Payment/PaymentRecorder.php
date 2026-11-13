<?php

namespace App\Payment;

final class PaymentRecorder
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * Records a payment on an invoice and returns the total paid so far.
     */
    public function record(int $invoiceId, float $amount): float
    {
        $pdo = $this->database->pdo();
        // Take the write lock up front: in a deferred transaction, the read-then-write upgrade
        // fails at once with "database is locked" (the busy timeout does not apply) when another
        // connection is writing. IMMEDIATE makes concurrent payments wait their turn instead.
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            $select = $pdo->prepare('SELECT paid FROM invoice_balances WHERE invoice_id = ?');
            $select->execute([$invoiceId]);
            $paid = (float) $select->fetchColumn();

            $pdo->prepare('INSERT INTO payments (invoice_id, amount, created_at) VALUES (?, ?, ?)')
                ->execute([$invoiceId, $amount, (new \DateTimeImmutable())->format(\DATE_ATOM)]);

            $newPaid = round($paid + $amount, 2);
            $pdo->prepare('UPDATE invoice_balances SET paid = ? WHERE invoice_id = ?')
                ->execute([$newPaid, $invoiceId]);

            $pdo->exec('COMMIT');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->exec('ROLLBACK');
            }

            throw $e;
        }

        return $newPaid;
    }

    /**
     * @return array{paid: float, payments_sum: float, payments: int}
     */
    public function balance(int $invoiceId): array
    {
        $pdo = $this->database->pdo();
        $paid = $pdo->prepare('SELECT paid FROM invoice_balances WHERE invoice_id = ?');
        $paid->execute([$invoiceId]);
        $sum = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS n FROM payments WHERE invoice_id = ?');
        $sum->execute([$invoiceId]);
        $row = $sum->fetch();

        return ['paid' => (float) $paid->fetchColumn(), 'payments_sum' => round((float) $row['total'], 2), 'payments' => (int) $row['n']];
    }
}
