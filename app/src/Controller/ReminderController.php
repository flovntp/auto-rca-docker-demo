<?php

namespace App\Controller;

use App\Invoice\InvoiceRepository;
use App\Invoice\SendPaymentReminder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class ReminderController
{
    public function __construct(
        private readonly InvoiceRepository $invoices,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /** Accepted at once: the worker sends the reminder. */
    #[Route('/invoices/{id<\d+>}/reminders', methods: ['POST'])]
    public function remind(int $id): JsonResponse
    {
        $this->invoices->find($id) ?? throw new NotFoundHttpException('Invoice not found.');
        $this->bus->dispatch(new SendPaymentReminder($id));

        return new JsonResponse(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
