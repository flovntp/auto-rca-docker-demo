<?php

namespace App\Tests\AutoRca;

use App\AutoRca\Incident;
use App\AutoRca\IncidentClaims;
use App\AutoRca\WorkerFailureSubscriber;
use App\Invoice\SendPaymentReminder;
use App\Triage\TriageIncident;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

final class WorkerFailureSubscriberTest extends TestCase
{
    private string $shareDir;
    /** @var list<object> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->shareDir = sys_get_temp_dir().'/auto-rca-test-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->shareDir.'/*') ?: [] as $claim) {
            rmdir($claim);
        }
        @rmdir($this->shareDir);
    }

    #[Test]
    public function aMessageGivenUpOnIsTriagedOnce(): void
    {
        $subscriber = $this->subscriber();

        $subscriber->onMessageFailed($this->failure(new SendPaymentReminder(2)));
        $subscriber->onMessageFailed($this->failure(new SendPaymentReminder(2)));

        self::assertCount(1, $this->dispatched);
        $incident = $this->dispatched[0]->incident;
        self::assertSame(Incident::SOURCE_WORKER, $incident->source);
        self::assertSame('message '.SendPaymentReminder::class, $incident->origin);
        // The handler's exception, not Messenger's wrapper.
        self::assertSame(\TypeError::class, $incident->class);
        self::assertSame('tests/AutoRca/WorkerFailureSubscriberTest.php', $incident->file);
    }

    #[Test]
    public function aMessageThatWillBeRetriedIsNotAnIncidentYet(): void
    {
        $event = $this->failure(new SendPaymentReminder(2));
        $event->setForRetry();

        $this->subscriber()->onMessageFailed($event);

        self::assertSame([], $this->dispatched);
    }

    #[Test]
    public function aFailedTriageIsNotTriagedInTurn(): void
    {
        $this->subscriber()->onMessageFailed($this->failure(new TriageIncident($this->anIncident())));

        self::assertSame([], $this->dispatched);
    }

    #[Test]
    public function nothingIsTriagedWhenAutoRcaIsDisabled(): void
    {
        $this->subscriber(enabled: false)->onMessageFailed($this->failure(new SendPaymentReminder(2)));

        self::assertSame([], $this->dispatched);
    }

    private function subscriber(bool $enabled = true): WorkerFailureSubscriber
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            $this->dispatched[] = $message;

            return new Envelope($message);
        });

        return new WorkerFailureSubscriber($bus, new IncidentClaims($this->shareDir), $enabled, 'abc1234', \dirname(__DIR__, 2));
    }

    private function failure(object $message): WorkerMessageFailedEvent
    {
        $envelope = new Envelope($message);

        return new WorkerMessageFailedEvent($envelope, 'async', new HandlerFailedException($envelope, [$this->typeError()]));
    }

    private function typeError(): \TypeError
    {
        return new \TypeError('App\Invoice\PaymentReminder::__construct(): Argument #1 ($to) must be of type string, null given');
    }

    private function anIncident(): Incident
    {
        return Incident::fromMessage(new \RuntimeException('boom'), new SendPaymentReminder(1), \dirname(__DIR__, 2), '');
    }
}
