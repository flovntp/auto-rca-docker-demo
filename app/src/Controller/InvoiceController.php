<?php

namespace App\Controller;

use App\Invoice\InvoiceRepository;
use App\Invoice\InvoiceSummary;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class InvoiceController
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly InvoiceSummary $summary,
    ) {
    }

    #[Route('/invoices/{id<\d+>}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $invoice = $this->invoices->find($id) ?? throw new NotFoundHttpException('Invoice not found.');

        return new JsonResponse($this->summary->summarize($invoice));
    }
}
