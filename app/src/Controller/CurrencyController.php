<?php

namespace App\Controller;

use App\Currency\ExchangeRates;
use App\Invoice\InvoiceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class CurrencyController
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly ExchangeRates $rates,
    ) {
    }

    /** An invoice's total in another currency, for foreign customers. */
    #[Route('/invoices/{id<\d+>}/total/{currency<[A-Z]{3}>}', methods: ['GET'])]
    public function total(int $id, string $currency): JsonResponse
    {
        $invoice = $this->invoices->find($id) ?? throw new NotFoundHttpException('Invoice not found.');
        $rate = $this->rates->rate('EUR', $currency);

        return new JsonResponse([
            'invoice' => $id,
            'currency' => $currency,
            'rate' => $rate,
            'total' => round(array_sum($invoice->lines) * $rate, 2),
        ]);
    }
}
