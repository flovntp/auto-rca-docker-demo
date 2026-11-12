<?php

namespace App\Controller;

use App\Invoice\InvoiceRepository;
use App\Payment\PaymentRecorder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class PaymentController
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly PaymentRecorder $payments,
    ) {
    }

    #[Route('/invoices/{id<\d+>}/payments', methods: ['POST'])]
    public function pay(int $id, Request $request): JsonResponse
    {
        $this->invoices->find($id) ?? throw new NotFoundHttpException('Invoice not found.');
        $amount = $request->getPayload()->get('amount');
        if (!is_numeric($amount) || $amount <= 0) {
            throw new BadRequestHttpException('"amount" must be a positive number.');
        }

        return new JsonResponse(['invoice' => $id, 'paid' => $this->payments->record($id, (float) $amount)], 201);
    }

    #[Route('/invoices/{id<\d+>}/payments', methods: ['GET'])]
    public function balance(int $id): JsonResponse
    {
        $this->invoices->find($id) ?? throw new NotFoundHttpException('Invoice not found.');

        return new JsonResponse(['invoice' => $id] + $this->payments->balance($id));
    }
}
