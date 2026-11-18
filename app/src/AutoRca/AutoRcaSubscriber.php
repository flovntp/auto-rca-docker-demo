<?php

namespace App\AutoRca;

use App\Triage\TriageIncident;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * On every server error (5xx) in production, once per distinct bug: hands
 * the incident to the worker, which triages it (see App\Triage). The worker's
 * own failures go through WorkerFailureSubscriber.
 */
final class AutoRcaSubscriber implements EventSubscriberInterface, ResetInterface
{
    private ?Incident $pending = null;

    public function __construct(
        private readonly MessageBusInterface $bus,
        #[Autowire(env: 'bool:RCA_ENABLED')] private readonly bool $enabled,
        #[Autowire(env: 'APP_REVISION')] private readonly string $revision,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly IncidentClaims $claims,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onException', -128],
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $e = $event->getThrowable();
        // A 404 or a 403 is not a bug: only server errors get an agent.
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return;
        }

        $incident = Incident::fromRequest($e, $event->getRequest(), $this->projectDir, $this->revision);
        if ($this->claims->claim($incident->signature)) {
            $this->pending = $incident;
        }
    }

    /**
     * Queued once the response is sent: the visitor doesn't wait for it, and
     * the web app holds no API key nor GitHub token, only the queue.
     */
    public function onTerminate(TerminateEvent $event): void
    {
        try {
            if (null !== $this->pending) {
                $this->bus->dispatch(new TriageIncident($this->pending));
            }
        } catch (\Throwable) {
            // The page has already failed: the triage must never make it worse.
        } finally {
            $this->reset();
        }
    }

    public function reset(): void
    {
        $this->pending = null;
    }
}
