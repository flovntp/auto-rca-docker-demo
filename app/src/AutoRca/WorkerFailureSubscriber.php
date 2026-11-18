<?php

namespace App\AutoRca;

use App\Triage\TriageIncident;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The worker's equivalent of a 500: a message whose handler threw, and that
 * Messenger gives up on after its retries (it goes to the "failed" transport).
 * A failure that a retry gets past (a locked database, a provider back up)
 * is not an incident. Once per distinct bug, like the web app's errors.
 */
final readonly class WorkerFailureSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private IncidentClaims $claims,
        #[Autowire(env: 'bool:RCA_ENABLED')] private bool $enabled,
        #[Autowire(env: 'APP_REVISION')] private string $revision,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // After Messenger's retry listener (priority 100), which decides willRetry().
        return [WorkerMessageFailedEvent::class => ['onMessageFailed', -128]];
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        // A triage that fails must not be triaged in turn: that would loop.
        if (!$this->enabled || $event->willRetry() || $message instanceof TriageIncident) {
            return;
        }

        // The handler's exception, not Messenger's wrapper: the signature and
        // the stack trace must point to the application's code.
        $e = $event->getThrowable();
        if ($e instanceof HandlerFailedException) {
            $e = $e->getPrevious() ?? $e;
        }

        $incident = Incident::fromMessage($e, $message, $this->projectDir, $this->revision);
        if (!$this->claims->claim($incident->signature)) {
            return;
        }

        try {
            $this->bus->dispatch(new TriageIncident($incident));
        } catch (\Throwable) {
            // The message has already failed: the triage must never stop the worker.
        }
    }
}
